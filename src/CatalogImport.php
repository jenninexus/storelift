<?php

declare(strict_types=1);

namespace StoreLift;

final class CatalogImport
{
    public const SOURCE_URL = 'https://jenninexus.com/products';
    private const HOST = 'jenninexus.com';
    private const MAX_BYTES = 512_000;
    private const MAX_PRODUCTS = 24;

    public static function fetch(string $url): array
    {
        if ($url !== self::SOURCE_URL) {
            throw new HttpError(422, 'unsupported_catalog', 'Enter https://jenninexus.com/products to import this catalog.');
        }
        if (!extension_loaded('curl') || !extension_loaded('dom')) {
            throw new HttpError(503, 'catalog_setup_required', 'Catalog import requires the PHP cURL and DOM extensions.');
        }

        $records = @dns_get_record(self::HOST, DNS_A | DNS_AAAA) ?: [];
        $addresses = [];
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? '';
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== false) {
                $addresses[] = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
            }
        }
        if ($addresses === []) {
            throw new HttpError(502, 'catalog_unavailable', 'JenniNexus could not be reached. Try again.');
        }

        $body = '';
        $curl = curl_init(self::SOURCE_URL);
        $options = [
            CURLOPT_RESOLVE => [self::HOST . ':443:' . implode(',', array_unique($addresses))],
            CURLOPT_PROXY => '',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'StoreLift Catalog Import',
            CURLOPT_HTTPHEADER => ['Accept: text/html'],
            CURLOPT_WRITEFUNCTION => static function (\CurlHandle $handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > self::MAX_BYTES) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ];
        if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
            $options[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
        }
        curl_setopt_array($curl, $options);
        $ok = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $type = strtolower(trim(explode(';', (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE))[0]));
        if ($ok !== true || $status !== 200 || $type !== 'text/html') {
            throw new HttpError(502, 'catalog_unavailable', 'JenniNexus did not return a readable catalog. Try again.');
        }

        return self::parse($body);
    }

    public static function parse(string $html): array
    {
        $dom = new \DOMDocument();
        if ($html === '' || strlen($html) > self::MAX_BYTES
            || !$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw self::invalidCatalog();
        }
        $xpath = new \DOMXPath($dom);
        $cards = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " product-widget ")]');
        if ($cards->length === 0 || $cards->length > self::MAX_PRODUCTS) {
            throw self::invalidCatalog();
        }

        $items = [];
        foreach ($cards as $card) {
            $sku = $card->getAttribute('data-sku');
            $status = $card->getAttribute('data-status');
            $title = self::text($xpath->query('.//h2[1]', $card)->item(0), 120);
            if (!preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/D', $sku) || isset($items[$sku]) || $title === ''
                || !in_array($status, ['free', 'membership', 'planned', 'sandbox', 'live'], true)) {
                throw self::invalidCatalog();
            }

            $detailId = $card->getAttribute('data-product-detail');
            $detail = null;
            if ($detailId === 'product-detail-' . $sku) {
                $detail = $xpath->query(
                    '//*[@id="' . $detailId . '" and contains(concat(" ", normalize-space(@class), " "), " product-detail-modal ")]',
                )->item(0);
            }
            $actions = [];
            $cardLinks = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " product-widget-actions ")]//a[@href]', $card);
            $detailLinks = $detail === null ? [] : $xpath->query(
                './/*[contains(concat(" ", normalize-space(@class), " "), " product-detail-modal__actions ")]//a[@href]',
                $detail,
            );
            foreach ([$cardLinks, $detailLinks] as $links) {
                foreach ($links as $link) {
                    $url = self::actionUrl($link->getAttribute('href'));
                    $label = self::text($link, 100);
                    if ($url === null || $label === '' || isset($actions[$url])) {
                        continue;
                    }
                    $actions[$url] = ['url' => $url, 'label' => $label, 'type' => self::actionType($url, $status)];
                }
            }
            if (count($actions) > 12) {
                throw self::invalidCatalog();
            }

            $items[$sku] = [
                'sku' => $sku,
                'title' => $title,
                'description' => self::text($xpath->query('./p[1]', $card)->item(0), 600),
                'status' => $status,
                'sourceUrl' => self::SOURCE_URL . '#' . $sku,
                'detail' => $detail === null ? '' : self::text($xpath->query(
                    './/p[contains(concat(" ", normalize-space(@class), " "), " lead ")][1]',
                    $detail,
                )->item(0), 800),
                'actions' => array_values($actions),
            ];
        }

        return array_values($items);
    }

    private static function text(?\DOMNode $node, int $limit): string
    {
        $text = trim(preg_replace('/[\p{Z}\p{Cc}]+/u', ' ', $node?->textContent ?? '') ?? '');
        preg_match('/^.{0,' . $limit . '}/u', $text, $prefix);
        return $prefix[0] ?? '';
    }

    private static function actionUrl(string $value): ?string
    {
        $url = trim($value);
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            $url = 'https://' . self::HOST . $url;
        }
        return self::publicActionUrl($url);
    }

    public static function publicActionUrl(string $url): ?string
    {
        if (strlen($url) > 1024 || str_contains($url, '\\') || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $parts = parse_url($url);
        $host = rtrim(strtolower($parts['host'] ?? ''), '.');
        if (strtolower($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])
            || ($parts['port'] ?? 443) !== 443 || !str_contains($host, '.')
            || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
            || preg_match('/\.[a-z][a-z0-9-]*$/D', $host) !== 1
            || preg_match('/(?:^|\.)(localhost|local|internal|test|invalid|example|home\.arpa)$/D', $host) === 1) {
            return null;
        }
        return $url;
    }

    private static function actionType(string $url, string $status): string
    {
        $parts = parse_url($url);
        $host = rtrim(strtolower($parts['host']), '.');
        $path = $parts['path'] ?? '/';
        if (in_array($host, ['paypal.me', 'www.paypal.me'], true)) {
            return 'tip';
        }
        if (in_array($host, ['patreon.com', 'www.patreon.com'], true)
            || ($host === self::HOST && rtrim($path, '/') === '/patreon')) {
            return 'membership';
        }
        if ($status === 'free' && (in_array($host, ['github.com', 'marketplace.visualstudio.com', 'open-vsx.org'], true)
            || ($host === self::HOST && in_array($path, ['/fin-sig', '/agency'], true)))) {
            return 'free';
        }
        return 'link';
    }

    private static function invalidCatalog(): HttpError
    {
        return new HttpError(502, 'catalog_unrecognized', 'JenniNexus did not return the expected product cards.');
    }
}
