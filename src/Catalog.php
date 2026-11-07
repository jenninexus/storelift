<?php

declare(strict_types=1);

namespace StoreLift;

final class Catalog
{
    public const SKU = 'creator-demo-kit';
    public const REVISION = '1';
    public const TITLE = 'Creator Demo Kit';
    public const CENTS = 300;
    public const CURRENCY = 'USD';

    public static function amount(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
