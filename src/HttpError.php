<?php

declare(strict_types=1);

namespace StoreLift;

final class HttpError extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $error,
        string $message,
    ) {
        parent::__construct($message);
    }
}
