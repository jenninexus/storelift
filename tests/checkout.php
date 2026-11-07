<?php

declare(strict_types=1);

use StoreLift\Catalog;
use StoreLift\Checkout;
use StoreLift\Config;
use StoreLift\Downloads;
use StoreLift\HttpError;
use StoreLift\OrderStore;
use StoreLift\PayPalGateway;

require dirname(__DIR__) . '/src/bootstrap.php';

// This gateway is only injected by this CLI test. HTTP always uses the real PayPal client.
final class PayPalFixture implements PayPalGateway
{
    public array $orders = [];
    public array $requests = [];
    public int $captures = 0;
    public string $captureStatus = 'COMPLETED';
    public bool $decline = false;

    public function createOrder(array $payload, string $requestId): array
    {
        $id = 'TESTORDER' . str_pad((string) (count($this->orders) + 1), 8, '0', STR_PAD_LEFT);
        $this->requests[] = $requestId;
        $this->orders[$id] = $payload + ['id' => $id, 'status' => 'CREATED'];
        $this->orders[$id]['purchase_units'][0]['payee'] = ['merchant_id' => 'TESTMERCHANT1'];
        return ['id' => $id];
    }

    public function getOrder(string $orderId): array
    {
        return $this->orders[$orderId];
    }

    public function captureOrder(string $orderId, string $requestId): void
    {
        $this->requests[] = $requestId;
        if ($this->decline) {
            throw new HttpError(409, 'instrument_declined', 'Fixture declined this payment.');
        }
        $this->captures++;
        $unit = &$this->orders[$orderId]['purchase_units'][0];
        $unit['payments']['captures'] = [[
            'id' => 'TESTCAP' . substr($orderId, -8), 'status' => $this->captureStatus, 'amount' => $unit['amount'],
        ]];
        $this->orders[$orderId]['status'] = 'COMPLETED';
    }

    public function verifyWebhook(array $headers, string $rawEvent): bool
    {
        return ($headers['test_signature'] ?? false) === true;
    }
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rejects(callable $action, string $code): void
{
    try {
        $action();
    } catch (HttpError $error) {
        check($error->error === $code, 'Expected ' . $code . ', received ' . $error->error);
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $code);
}

function event(string $id, string $type, array $resource): string
{
    return json_encode(['id' => $id, 'event_type' => $type, 'resource' => $resource], JSON_THROW_ON_ERROR);
}

$dir = sys_get_temp_dir() . '/storelift-check-' . bin2hex(random_bytes(8));
mkdir($dir, 0700);
try {
    file_put_contents($dir . '/fixture.env', "PAYPAL_ENV=sandbox\n");
    putenv('STORELIFT_ENV_FILE=' . $dir . '/fixture.env');
    putenv('STORELIFT_STATE_DIR=' . $dir);
    putenv('PAYPAL_ENV=sandbox');
    putenv('PAYPAL_SANDBOX_CLIENT_ID=fixture-app');
    putenv('PAYPAL_SANDBOX_CLIENT_SECRET=fixture-secret');
    putenv('PAYPAL_SANDBOX_MERCHANT_ID=');
    putenv('PAYPAL_SANDBOX_WEBHOOK_ID=fixture-webhook');
    putenv('STORELIFT_DOWNLOAD_KEY=' . bin2hex(random_bytes(32)));

    $config = new Config(dirname(__DIR__));
    $asset = new ZipArchive();
    $asset->open($config->kitPath(), ZipArchive::CREATE);
    $asset->addFromString('proof.txt', 'StoreLift test fixture');
    $asset->close();
    $bytes = file_get_contents($config->kitPath());

    $store = new OrderStore($dir . '/orders.sqlite');
    $paypal = new PayPalFixture();
    $checkout = new Checkout($config, $store, $paypal);
    $download = new Downloads($config, $store);
    $session = 'buyer-session';
    $attempt = str_repeat('a', 32);

    rejects(fn () => $checkout->create($session, 'not-our-product', $attempt), 'invalid_product');
    $id = $checkout->create($session, Catalog::SKU, $attempt);
    check($id === $checkout->create($session, Catalog::SKU, $attempt), 'Retry must use the original PayPal order');
    check(count($paypal->orders) === 1, 'Retry created another PayPal order');
    check($paypal->orders[$id]['purchase_units'][0]['amount']['value'] === '3.00', 'Incorrect server price');
    rejects(fn () => $checkout->create('another-session', Catalog::SKU, $attempt), 'order_not_found');
    rejects(fn () => $checkout->capture('another-session', $id), 'order_not_found');
    rejects(fn () => $checkout->capture($session, $id), 'order_not_captured');
    rejects(fn () => $download->file($session, $id, '9999999999', 'forged'), 'download_denied');

    $paypal->orders[$id]['status'] = 'APPROVED';
    $paypal->orders[$id]['purchase_units'][0]['payee']['merchant_id'] = 'WRONGMERCHANT';
    rejects(fn () => $checkout->capture($session, $id), 'order_mismatch');
    check($paypal->captures === 0, 'Wrong merchant reached capture');
    $paypal->orders[$id]['purchase_units'][0]['payee']['merchant_id'] = 'TESTMERCHANT1';

    $paypal->decline = true;
    rejects(fn () => $checkout->capture($session, $id), 'instrument_declined');
    check(!isset($download->receipt($store->owned($id, $session))['downloadUrl']), 'Declined payment granted a link');
    $paypal->decline = false;
    $paypal->captureStatus = 'PENDING';
    $order = $checkout->capture($session, $id);
    check($order['state'] === 'pending', 'Pending capture must stay pending');
    check(!isset($download->receipt($order)['downloadUrl']), 'Pending capture granted a link');
    $captureId = $order['capture_id'];
    $completed = event('WH-COMPLETED-1', 'PAYMENT.CAPTURE.COMPLETED', [
        'id' => $captureId, 'supplementary_data' => ['related_ids' => ['order_id' => $id]],
    ]);
    rejects(fn () => $checkout->webhook([], $completed), 'invalid_signature');
    $paypal->orders[$id]['purchase_units'][0]['payments']['captures'][0]['status'] = 'COMPLETED';
    $checkout->webhook(['test_signature' => true], $completed);
    $checkout->webhook(['test_signature' => true], $completed);

    $receipt = $download->receipt($checkout->capture($session, $id));
    check($receipt['captureId'] === $captureId && $paypal->captures === 1, 'Paid retry duplicated the capture');
    check($paypal->requests[1] === $paypal->requests[2], 'Capture retry changed its idempotency key');
    parse_str(parse_url($receipt['downloadUrl'], PHP_URL_QUERY), $link);
    check($download->file($session, $id, $link['expires'], $link['sig']) === $bytes, 'Wrong download bytes');
    rejects(fn () => $download->file('another-session', $id, $link['expires'], $link['sig']), 'order_not_found');
    rejects(fn () => $download->file($session, $id, '1', $link['sig']), 'download_denied');
    rejects(fn () => $download->file($session, $id, $link['expires'], str_repeat('0', 64)), 'download_denied');
    file_put_contents($config->kitPath(), 'changed file');
    rejects(fn () => $download->file($session, $id, $link['expires'], $link['sig']), 'asset_unavailable');
    file_put_contents($config->kitPath(), $bytes);

    $refund = event('WH-REFUNDED-1', 'PAYMENT.CAPTURE.REFUNDED', [
        'id' => 'REFUND-EXAMPLE-1',
        'links' => [['rel' => 'up', 'href' => 'https://api-m.sandbox.paypal.com/v2/payments/captures/' . $captureId]],
    ]);
    $checkout->webhook(['test_signature' => true], $refund);
    $checkout->webhook(['test_signature' => true], $refund);
    rejects(fn () => $download->file($session, $id, $link['expires'], $link['sig']), 'download_denied');
    check($checkout->capture($session, $id)['state'] === 'revoked', 'A retry restored a revoked entitlement');

    $lateId = $checkout->create($session, Catalog::SKU, str_repeat('b', 32));
    $lateCapture = 'TESTCAP' . substr($lateId, -8);
    $checkout->webhook(['test_signature' => true], event('WH-REVERSED-2', 'PAYMENT.CAPTURE.REVERSED', ['id' => $lateCapture]));
    $paypal->orders[$lateId]['status'] = 'APPROVED';
    $paypal->captureStatus = 'COMPLETED';
    check($checkout->capture($session, $lateId)['state'] === 'revoked', 'Out-of-order reversal granted an entitlement');

    putenv('PAYPAL_ENV=live');
    $refused = false;
    try {
        new Config(dirname(__DIR__));
    } catch (RuntimeException $error) {
        $refused = str_contains($error->getMessage(), 'PAYPAL_ENV=sandbox');
    }
    check($refused, 'Live configuration was accepted');
    echo "Checkout fixture passed: order and capture retries, payee and session checks, decline, pending, download integrity, expiry, webhook and refund handling.\n";
} finally {
    unset($download, $checkout, $store);
    foreach (['orders.sqlite', 'fixture.env', 'assets/creator-demo-kit-v1.zip'] as $file) {
        if (is_file($dir . '/' . $file)) {
            unlink($dir . '/' . $file);
        }
    }
    foreach (['/assets', '/sessions', ''] as $part) {
        if (is_dir($dir . $part)) {
            rmdir($dir . $part);
        }
    }
}
