<?php

declare(strict_types=1);

namespace StoreLift;

final class Config
{
    public readonly string $stateDir;
    public readonly string $clientId;
    public readonly string $clientSecret;
    public readonly string $merchantId;
    public readonly string $webhookId;
    public readonly string $downloadKey;
    public readonly bool $secureCookies;
    public readonly string $aiEndpoint;
    public readonly string $aiModel;
    public readonly string $aiKey;

    public function __construct(string $root)
    {
        $public = realpath($root . '/public');
        $profile = getenv('LOCALAPPDATA');
        $profileFile = $profile ? $profile . '/StoreLift/secrets/sandbox.env' : '';
        $file = getenv('STORELIFT_ENV_FILE') ?: (is_file($profileFile) ? $profileFile : $root . '/.env.local');
        $values = [];
        if (is_file($file)) {
            $path = realpath($file);
            if ($path === false || self::inside($path, $public)) {
                throw new \RuntimeException('The configuration file must be outside public/.');
            }
            $values = @parse_ini_file($path, false, INI_SCANNER_RAW);
            if (!is_array($values)) {
                throw new \RuntimeException('Cannot read the StoreLift configuration file.');
            }
        } elseif (getenv('STORELIFT_ENV_FILE') !== false) {
            throw new \RuntimeException('The configured StoreLift environment file is missing.');
        }

        $read = static function (string $key, string $default = '') use ($values): string {
            $environment = getenv($key);
            $value = $environment !== false ? $environment : ($values[$key] ?? $default);
            if (!is_string($value)) {
                throw new \RuntimeException('Invalid configuration value for ' . $key);
            }
            return trim($value);
        };

        if ($read('PAYPAL_ENV', 'sandbox') !== 'sandbox') {
            throw new \RuntimeException('StoreLift requires PAYPAL_ENV=sandbox.');
        }

        $this->clientId = $read('PAYPAL_SANDBOX_CLIENT_ID');
        $this->clientSecret = $read('PAYPAL_SANDBOX_CLIENT_SECRET');
        $this->merchantId = $read('PAYPAL_SANDBOX_MERCHANT_ID');
        $this->webhookId = $read('PAYPAL_SANDBOX_WEBHOOK_ID');
        $this->downloadKey = $read('STORELIFT_DOWNLOAD_KEY');
        $this->aiEndpoint = $read('STORELIFT_AI_ENDPOINT');
        $this->aiModel = $read('STORELIFT_AI_MODEL');
        $this->aiKey = $read('STORELIFT_AI_API_KEY');
        $this->secureCookies = $read('STORELIFT_HTTPS', '0') === '1'
            || ($_SERVER['HTTPS'] ?? '') === 'on';

        $state = $read('STORELIFT_STATE_DIR', $root . '/var');
        $this->stateDir = self::privateDirectory($state, $public);
        self::privateDirectory($this->stateDir . '/sessions', $public);
        self::privateDirectory($this->stateDir . '/assets', $public);
    }

    public function checkoutReady(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== ''
            && ($this->merchantId === '' || preg_match('/^[A-Z0-9]{13}$/D', $this->merchantId) === 1)
            && preg_match('/^[a-f0-9]{64}$/iD', $this->downloadKey) === 1
            && is_readable($this->kitPath());
    }

    public function kitPath(): string
    {
        return $this->stateDir . '/assets/creator-demo-kit-v1.zip';
    }

    public function aiReady(): bool
    {
        return $this->aiEndpoint !== '' && $this->aiModel !== '' && $this->aiKey !== '';
    }

    private static function privateDirectory(string $path, string|false $public): string
    {
        if ($public === false || self::inside($path, $public)) {
            throw new \RuntimeException('StoreLift state must be outside public/.');
        }
        // Check the nearest existing ancestor before mkdir so aliases cannot create a public directory.
        $ancestor = $path;
        while (!is_dir($ancestor) && dirname($ancestor) !== $ancestor) {
            $ancestor = dirname($ancestor);
        }
        $resolvedAncestor = realpath($ancestor);
        if ($resolvedAncestor !== false && self::inside($resolvedAncestor, $public)) {
            throw new \RuntimeException('StoreLift state must be outside public/.');
        }
        if (!is_dir($path) && !@mkdir($path, 0700, true) && !is_dir($path)) {
            throw new \RuntimeException('Cannot create the private state directory.');
        }
        $resolved = realpath($path);
        if ($resolved === false || self::inside($resolved, $public)) {
            throw new \RuntimeException('StoreLift state must be outside public/.');
        }
        return $resolved;
    }

    private static function inside(string $path, string|false $directory): bool
    {
        if ($directory === false) {
            return false;
        }
        $path = str_replace('\\', '/', $path);
        $directory = rtrim(str_replace('\\', '/', $directory), '/');
        if (PHP_OS_FAMILY === 'Windows') {
            $path = strtolower($path);
            $directory = strtolower($directory);
        }
        return $path === $directory || str_starts_with($path, $directory . '/');
    }
}
