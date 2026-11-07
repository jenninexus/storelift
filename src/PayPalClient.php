<?php

declare(strict_types=1);

namespace StoreLift;

final class PayPalClient implements PayPalGateway
{
    private const BASE = 'https://api-m.sandbox.paypal.com';
    private ?string $accessToken = null;

    public function __construct(private readonly Config $config)
    {
    }

    public function createOrder(array $payload, string $requestId): array
    {
        return $this->api('POST', '/v2/checkout/orders', self::json($payload), $requestId);
    }

    public function getOrder(string $orderId): array
    {
        return $this->api('GET', '/v2/checkout/orders/' . rawurlencode($orderId));
    }

    public function captureOrder(string $orderId, string $requestId): void
    {
        $this->api('POST', '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', '{}', $requestId);
    }

    public function verifyWebhook(array $headers, string $rawEvent): bool
    {
        if ($this->config->webhookId === '') {
            return false;
        }
        $fields = ['webhook_id' => $this->config->webhookId];
        foreach (['auth_algo', 'cert_url', 'transmission_id', 'transmission_sig', 'transmission_time'] as $name) {
            $key = 'HTTP_PAYPAL_' . strtoupper($name);
            if (!isset($headers[$key]) || !is_string($headers[$key]) || trim($headers[$key]) === '') {
                return false;
            }
            $fields[$name] = $headers[$key];
        }
        $body = substr(self::json($fields), 0, -1) . ',"webhook_event":' . $rawEvent . '}';
        $result = $this->api('POST', '/v1/notifications/verify-webhook-signature', $body);
        return ($result['verification_status'] ?? '') === 'SUCCESS';
    }

    public function authenticate(): void
    {
        $this->token();
    }

    private function token(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }
        $response = $this->request('POST', '/v1/oauth2/token', 'grant_type=client_credentials', [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ], $this->config->clientId . ':' . $this->config->clientSecret);
        $token = $response['access_token'] ?? null;
        if (!is_string($token) || $token === '' || str_contains($token, "\n") || str_contains($token, "\r")) {
            throw new HttpError(502, 'paypal_auth_failed', 'PayPal sandbox authentication failed.');
        }
        return $this->accessToken = $token;
    }

    private function api(string $method, string $path, ?string $body = null, ?string $requestId = null): array
    {
        $headers = ['Accept: application/json', 'Authorization: Bearer ' . $this->token()];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        if ($requestId !== null) {
            $headers[] = 'Prefer: return=representation';
            $headers[] = 'PayPal-Request-Id: ' . $requestId;
        }
        return $this->request($method, $path, $body, $headers);
    }

    private function request(string $method, string $path, ?string $body, array $headers, ?string $basic = null): array
    {
        $handle = curl_init(self::BASE . $path);
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 18,
        ];
        if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
            $options[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
        }
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        if ($basic !== null) {
            $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $options[CURLOPT_USERPWD] = $basic;
        }
        curl_setopt_array($handle, $options);
        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        unset($handle);

        $data = is_string($response) ? json_decode($response, true) : null;
        if ($status >= 200 && $status < 300 && is_array($data)) {
            return $data;
        }

        $issue = $data['details'][0]['issue'] ?? '';
        if ($issue === 'INSTRUMENT_DECLINED') {
            throw new HttpError(409, 'instrument_declined', 'PayPal declined this sandbox funding source.');
        }
        if ($issue === 'ORDER_ALREADY_CAPTURED') {
            throw new HttpError(409, 'order_already_captured', 'PayPal already captured this order.');
        }
        if ($path === '/v1/oauth2/token' && in_array($status, [400, 401, 403], true)) {
            throw new HttpError(502, 'paypal_auth_failed', 'PayPal rejected the sandbox app credentials.');
        }
        if ($issue === 'ORDER_NOT_APPROVED') {
            throw new HttpError(409, 'order_not_approved', 'Approve the order in PayPal before retrying.');
        }

        throw new HttpError(502, 'paypal_unavailable', 'PayPal sandbox could not finish the request. You can retry.');
    }

    private static function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
