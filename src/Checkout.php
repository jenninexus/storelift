<?php

declare(strict_types=1);

namespace StoreLift;

final class Checkout
{
    public function __construct(
        private readonly Config $config,
        private readonly OrderStore $store,
        private readonly PayPalGateway $paypal,
    ) {
    }

    public function create(string $owner, string $sku, string $attempt): string
    {
        $this->requireReady();
        if ($sku !== Catalog::SKU || preg_match('/^[a-f0-9]{32}$/D', $attempt) !== 1) {
            throw new HttpError(400, 'invalid_product', 'Choose a valid StoreLift product.');
        }
        $hash = hash_file('sha256', $this->config->kitPath());
        if ($hash === false) {
            throw new HttpError(503, 'asset_unavailable', 'The demo kit is temporarily unavailable.');
        }
        $order = $this->store->startAttempt($attempt, $owner, $this->config->merchantId, $hash);
        if ($order['paypal_id'] !== null) {
            return $order['paypal_id'];
        }
        if (time() - (int) $order['created_at'] > 7200) {
            throw new HttpError(409, 'attempt_expired', 'Reload StoreLift to start a new checkout.');
        }
        if (!hash_equals($order['asset_hash'], $hash)) {
            throw new HttpError(409, 'asset_changed', 'The kit changed. Reload StoreLift to start a new checkout.');
        }

        $created = $this->paypal->createOrder([
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order['sku'],
                'custom_id' => 'storelift:' . $attempt,
                'description' => Catalog::TITLE . ' v' . $order['revision'] . ' (Sandbox)',
                'amount' => ['currency_code' => $order['currency'], 'value' => Catalog::amount((int) $order['cents'])],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
            ]]],
        ], 'sl-new-' . $attempt);

        $paypalId = $created['id'] ?? '';
        if (!self::isPayPalId($paypalId)) {
            throw new HttpError(502, 'invalid_paypal_order', 'PayPal did not return a usable sandbox order.');
        }
        $order['paypal_id'] = $paypalId;
        $unit = $this->verifyOrder($order, $this->paypal->getOrder($paypalId));
        $this->store->linkOrder($attempt, $paypalId, $unit['payee']['merchant_id']);
        return $paypalId;
    }

    public function capture(string $owner, string $paypalId): array
    {
        $this->requireReady();
        $order = $this->store->owned($paypalId, $owner);
        if ($order['state'] === 'paid' || $order['state'] === 'revoked') {
            return $order;
        }
        $remote = $this->paypal->getOrder($paypalId);
        $this->verifyOrder($order, $remote);
        if (($remote['status'] ?? '') === 'APPROVED') {
            try {
                $this->paypal->captureOrder($paypalId, 'sl-cap-' . $order['attempt_id']);
            } catch (HttpError $error) {
                if ($error->error !== 'order_already_captured') {
                    throw $error;
                }
            }
            $remote = $this->paypal->getOrder($paypalId);
        }
        return $this->reconcile($order, $remote);
    }

    public function webhook(array $headers, string $raw): void
    {
        if ($this->config->webhookId === '' || $this->config->clientId === '' || $this->config->clientSecret === '') {
            throw new HttpError(503, 'webhook_not_configured', 'Sandbox webhooks are not configured.');
        }
        $event = json_decode($raw, true);
        if (!is_array($event) || !self::isPayPalId($event['id'] ?? '') || !is_string($event['event_type'] ?? null)) {
            throw new HttpError(400, 'invalid_event', 'Invalid PayPal event.');
        }
        if (!$this->paypal->verifyWebhook($headers, $raw)) {
            throw new HttpError(401, 'invalid_signature', 'PayPal event signature could not be verified.');
        }
        $eventId = $event['id'];
        if ($this->store->eventSeen($eventId)) {
            return;
        }

        $type = $event['event_type'];
        $resource = $event['resource'] ?? [];
        if (!is_array($resource)) {
            throw new HttpError(400, 'invalid_resource', 'Invalid PayPal event resource.');
        }
        if ($type === 'PAYMENT.CAPTURE.REFUNDED' || $type === 'PAYMENT.CAPTURE.REVERSED') {
            $captureId = $this->revokedCaptureId($resource, $type);
            if ($captureId === null) {
                throw new HttpError(422, 'capture_missing', 'PayPal did not identify the revoked capture.');
            }
            $this->store->revoke($captureId, $eventId);
            return;
        }
        if ($type === 'PAYMENT.CAPTURE.COMPLETED' && self::isPayPalId($resource['id'] ?? '')) {
            $paypalId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;
            $order = $this->store->webhookOrder(self::isPayPalId($paypalId) ? $paypalId : null, $resource['id']);
            if ($order !== null) {
                $updated = $this->reconcile($order, $this->paypal->getOrder($order['paypal_id']), $resource['id']);
                if ($updated['state'] === 'pending') {
                    throw new HttpError(503, 'payment_pending', 'Waiting for PayPal to report a completed capture.');
                }
            }
        }
        $this->store->markEvent($eventId);
    }

    public static function isPayPalId(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[A-Z0-9-]{8,80}$/D', $value) === 1;
    }

    private function requireReady(): void
    {
        if (!$this->config->checkoutReady()) {
            throw new HttpError(503, 'checkout_setup_required', 'StoreLift needs sandbox app credentials and the demo kit.');
        }
    }

    private function verifyOrder(array $order, array $remote): array
    {
        $units = $remote['purchase_units'] ?? null;
        $unit = $units[0] ?? [];
        $merchant = $unit['payee']['merchant_id'] ?? '';
        if (($remote['id'] ?? '') !== $order['paypal_id']
            || ($remote['intent'] ?? '') !== 'CAPTURE'
            || !is_array($units) || count($units) !== 1
            || ($unit['reference_id'] ?? '') !== $order['sku']
            || ($unit['custom_id'] ?? '') !== 'storelift:' . $order['attempt_id']
            || !$this->matchesAmount($order, $unit['amount'] ?? [])
            || !is_string($merchant) || preg_match('/^[A-Z0-9]{13}$/D', $merchant) !== 1
            || ($order['merchant_id'] !== '' && !hash_equals($order['merchant_id'], $merchant))) {
            throw new HttpError(409, 'order_mismatch', 'PayPal order details do not match this checkout.');
        }
        return $unit;
    }

    private function reconcile(array $order, array $remote, ?string $expectedCapture = null): array
    {
        $unit = $this->verifyOrder($order, $remote);
        $captures = $unit['payments']['captures'] ?? [];
        if (($remote['status'] ?? '') !== 'COMPLETED' || !is_array($captures) || count($captures) !== 1) {
            throw new HttpError(409, 'order_not_captured', 'PayPal has not reported a capture for this order.');
        }
        $capture = $captures[0];
        $id = $capture['id'] ?? '';
        if (!self::isPayPalId($id) || ($expectedCapture !== null && $id !== $expectedCapture)
            || !$this->matchesAmount($order, $capture['amount'] ?? [])) {
            throw new HttpError(409, 'capture_mismatch', 'PayPal capture details do not match this checkout.');
        }
        $state = match ($capture['status'] ?? '') {
            'COMPLETED' => 'paid',
            'PENDING' => 'pending',
            'REFUNDED', 'PARTIALLY_REFUNDED', 'REVERSED' => 'revoked',
            default => throw new HttpError(409, 'capture_failed', 'PayPal did not complete this sandbox payment.'),
        };
        return $this->store->recordCapture($order['paypal_id'], $id, $state);
    }

    private function matchesAmount(array $order, mixed $amount): bool
    {
        return is_array($amount) && ($amount['currency_code'] ?? '') === $order['currency']
            && ($amount['value'] ?? '') === Catalog::amount((int) $order['cents']);
    }

    private function revokedCaptureId(array $resource, string $type): ?string
    {
        $related = $resource['supplementary_data']['related_ids']['capture_id'] ?? null;
        if (self::isPayPalId($related)) {
            return $related;
        }
        foreach ($resource['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'up' && preg_match(
                '~^https://api(?:-m)?\.sandbox\.paypal\.com/v2/payments/captures/([A-Z0-9-]{8,80})$~D',
                $link['href'] ?? '',
                $match,
            ) === 1) {
                return $match[1];
            }
        }
        return $type === 'PAYMENT.CAPTURE.REVERSED' && self::isPayPalId($resource['id'] ?? null)
            ? $resource['id'] : null;
    }
}
