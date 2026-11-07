<?php

declare(strict_types=1);

use StoreLift\Catalog;
use StoreLift\CatalogReview;
use StoreLift\Checkout;
use StoreLift\Config;
use StoreLift\Downloads;
use StoreLift\HttpError;
use StoreLift\OrderStore;
use StoreLift\PayPalClient;

$base = getenv('STORELIFT_BASE_PATH') ?: '';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$validBase = $base === '' || preg_match('~^(/[a-z0-9-]+)+$~D', $base) === 1;
$path = $validBase && is_string($requestPath) && str_starts_with($requestPath, $base . '/')
    ? substr($requestPath, strlen($base)) : null;
if (PHP_SAPI === 'cli-server' && $base === '' && in_array($path, ['/assets/app.js', '/assets/store.css'], true)) {
    return false;
}

ini_set('display_errors', '0');
require dirname(__DIR__) . '/src/bootstrap.php';

function respond(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonBody(int $max = 8192): string
{
    $type = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($type !== 'application/json') {
        throw new HttpError(415, 'json_required', 'Send an application/json request.');
    }
    $body = file_get_contents('php://input', length: $max + 1);
    if ($body === false || strlen($body) > $max) {
        throw new HttpError(413, 'body_too_large', 'The request is too large.');
    }
    return $body;
}

function inputString(array $input, string $name): string
{
    $value = $input[$name] ?? '';
    if (!is_string($value) || strlen($value) > 256) {
        throw new HttpError(400, 'invalid_input', 'Invalid request value.');
    }
    return $value;
}

function requireMethod(string $actual, string $expected): void
{
    if ($actual !== $expected) {
        header('Allow: ' . $expected);
        throw new HttpError(405, 'method_not_allowed', 'This request method is unavailable.');
    }
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Cross-Origin-Opener-Policy: same-origin-allow-popups');

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($path, ['/', '/api/orders', '/api/capture', '/api/download', '/api/webhook', '/api/catalog'], true)) {
        throw new HttpError(404, 'not_found', 'This StoreLift page does not exist.');
    }

    $config = new Config(dirname(__DIR__));
    $store = new OrderStore($config->stateDir . '/orders.sqlite');
    $checkout = new Checkout($config, $store, new PayPalClient($config));
    $downloads = new Downloads($config, $store);
    if ($path === '/api/webhook') {
        requireMethod($method, 'POST');
        $checkout->webhook($_SERVER, jsonBody(262144));
        http_response_code(204);
        exit;
    }

    session_name('storelift_session');
    session_save_path($config->stateDir . '/sessions');
    session_set_cookie_params(['path' => $base . '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $config->secureCookies]);
    if (!session_start(['use_strict_mode' => 1, 'use_only_cookies' => 1, 'gc_maxlifetime' => 86400])) {
        throw new HttpError(503, 'session_unavailable', 'StoreLift could not start a checkout session.');
    }
    $_SESSION['owner'] ??= bin2hex(random_bytes(32));
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    $owner = $_SESSION['owner'];
    $csrf = $_SESSION['csrf'];
    $draft = $_SESSION['catalogDraft'] ?? null;
    $approved = $_SESSION['catalogApproved'] ?? null;
    session_write_close();

    if ($path === '/') {
        requireMethod($method, 'GET');
        $ready = $config->checkoutReady();
        $latest = $store->latest($owner);
        $receipt = $latest !== null && $ready ? $downloads->receipt($latest) : null;
        $attempt = bin2hex(random_bytes(16));
        $nonce = base64_encode(random_bytes(18));
        $paypalHosts = 'https://*.paypal.com https://*.paypalobjects.com';
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-$nonce' $paypalHosts; "
            . "style-src 'self' 'unsafe-inline' $paypalHosts; img-src 'self' data: $paypalHosts; "
            . "connect-src 'self' $paypalHosts; frame-src $paypalHosts; object-src 'none'; base-uri 'none'; frame-ancestors 'none'");
        header('Content-Type: text/html; charset=utf-8');
        require dirname(__DIR__) . '/src/storefront.php';
        exit;
    }

    if ($path === '/api/download') {
        requireMethod($method, 'GET');
        $bytes = $downloads->file($owner, inputString($_GET, 'order'), inputString($_GET, 'expires'), inputString($_GET, 'sig'));
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="creator-demo-kit-v1.zip"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    }

    if ($path === '/api/catalog' && $method === 'GET') {
        if ($approved === null) {
            throw new HttpError(404, 'catalog_unapproved', 'Approve a catalog before exporting it.');
        }
        header('Content-Disposition: attachment; filename="storelift-catalog.json"');
        respond($approved);
    }

    requireMethod($method, 'POST');
    if (!hash_equals($csrf, $_SERVER['HTTP_X_STORELIFT_CSRF'] ?? '')) {
        throw new HttpError(403, 'invalid_session', 'Refresh StoreLift to start a new checkout session.');
    }
    $data = json_decode(jsonBody($path === '/api/catalog' ? 65536 : 8192), true);
    if (!is_array($data)) {
        throw new HttpError(400, 'invalid_json', 'Send a valid JSON request.');
    }
    if ($path === '/api/catalog') {
        respond(CatalogReview::apply($config, $data, $draft, $owner));
    }
    if ($path === '/api/orders') {
        $id = $checkout->create($owner, inputString($data, 'sku'), inputString($data, 'attempt'));
        respond(['id' => $id], 201);
    }

    $id = inputString($data, 'orderId');
    if (!Checkout::isPayPalId($id)) {
        throw new HttpError(400, 'invalid_order', 'Choose a valid PayPal order.');
    }
    respond($downloads->receipt($checkout->capture($owner, $id)));
} catch (HttpError $error) {
    respond(['error' => $error->error, 'message' => $error->getMessage()], $error->status);
} catch (Throwable $error) {
    error_log(sprintf('StoreLift %s at %s:%d', get_class($error), basename($error->getFile()), $error->getLine()));
    respond(['error' => 'storelift_unavailable', 'message' => 'StoreLift is temporarily unavailable.'], 503);
}
