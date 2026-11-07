<?php

declare(strict_types=1);

namespace StoreLift;

final class Downloads
{
    private const TTL = 600;

    public function __construct(private readonly Config $config, private readonly OrderStore $store)
    {
    }

    public function receipt(array $order): array
    {
        $receipt = ['state' => $order['state'], 'orderId' => $order['paypal_id']];
        if ($order['state'] !== 'paid') {
            return $receipt;
        }

        $expires = time() + self::TTL;
        return $receipt + [
            'captureId' => $order['capture_id'],
            'amount' => Catalog::amount((int) $order['cents']) . ' ' . $order['currency'],
            'downloadUrl' => 'api/download?' . http_build_query([
                'order' => $order['paypal_id'], 'expires' => $expires, 'sig' => $this->sign($order, $expires),
            ]),
        ];
    }

    public function file(string $owner, string $paypalId, string $expires, string $signature): string
    {
        $order = $this->store->owned($paypalId, $owner);
        if ($order['state'] !== 'paid' || preg_match('/^[0-9]{1,12}$/D', $expires) !== 1
            || (int) $expires <= time() || !hash_equals($this->sign($order, (int) $expires), $signature)) {
            throw new HttpError(403, 'download_denied', 'This link is unavailable. Open StoreLift to refresh the receipt.');
        }
        $path = $this->config->kitPath();
        $bytes = is_readable($path) ? file_get_contents($path, length: 2_000_001) : false;
        if ($bytes === false || strlen($bytes) > 2_000_000 || !hash_equals($order['asset_hash'], hash('sha256', $bytes))) {
            throw new HttpError(503, 'asset_unavailable', 'This version of the demo kit is unavailable.');
        }
        return $bytes;
    }

    private function sign(array $order, int $expires): string
    {
        if (preg_match('/^[a-f0-9]{64}$/iD', $this->config->downloadKey) !== 1) {
            throw new HttpError(503, 'download_setup_required', 'A download signing key is required.');
        }
        $binding = implode(':', [$order['owner'], $order['paypal_id'], $order['capture_id'], $order['asset_hash'], $expires]);
        return hash_hmac('sha256', $binding, hex2bin($this->config->downloadKey));
    }
}
