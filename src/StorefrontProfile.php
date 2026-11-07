<?php

declare(strict_types=1);

namespace StoreLift;

final class StorefrontProfile
{
    public static function load(string $root): array
    {
        $defaults = [
            'name' => 'StoreLift',
            'tagline' => 'Import a catalog, shape the listings and preview a storefront that keeps free tools and creator support easy to find.',
            'catalog_url' => CatalogImport::SOURCE_URL,
        ];
        $path = $root . '/.local/storefront.json';
        if (!is_file($path)) {
            return $defaults;
        }
        $raw = @file_get_contents($path, length: 4097);
        $profile = is_string($raw) && strlen($raw) <= 4096 ? json_decode($raw, true) : null;
        if (!is_array($profile) || array_diff_key($profile, $defaults) !== []) {
            error_log('StoreLift storefront profile is invalid. Using defaults.');
            return $defaults;
        }
        $profile += $defaults;
        if (!self::text($profile['name'], 80) || !self::text($profile['tagline'], 240)
            || !in_array($profile['catalog_url'], [null, CatalogImport::SOURCE_URL], true)) {
            error_log('StoreLift storefront profile is invalid. Using defaults.');
            return $defaults;
        }
        return $profile;
    }

    private static function text(mixed $value, int $max): bool
    {
        return is_string($value) && trim($value) !== '' && strlen($value) <= $max
            && preg_match('/[\p{Cc}]/u', $value) === 0;
    }
}
