<?php

declare(strict_types=1);

namespace StoreLift;

interface PayPalGateway
{
    public function createOrder(array $payload, string $requestId): array;

    public function getOrder(string $orderId): array;

    public function captureOrder(string $orderId, string $requestId): void;

    public function verifyWebhook(array $headers, string $rawEvent): bool;
}
