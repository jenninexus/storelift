<?php

declare(strict_types=1);

namespace StoreLift;

final class CatalogCsv
{
    public const MAX_BYTES = 24576;
    private const COLUMNS = ['sku', 'title', 'action', 'description', 'url'];
    private const ACTIONS = ['free' => 'Open free item', 'tip' => 'Support the creator', 'membership' => 'See membership', 'link' => 'Open original listing'];

    public static function parse(mixed $csv): array
    {
        if (!is_string($csv) || $csv === '' || strlen($csv) > self::MAX_BYTES
            || preg_match('//u', $csv) !== 1 || str_contains($csv, "\0")) {
            throw self::invalid('Choose a UTF-8 CSV file under 24 KB.');
        }
        $stream = fopen('php://memory', 'r+');
        try {
            fwrite($stream, str_starts_with($csv, "\xef\xbb\xbf") ? substr($csv, 3) : $csv);
            rewind($stream);
            $header = fgetcsv($stream, null, ',', '"', '');
            if ($header === false || count($header) !== count(self::COLUMNS) || array_diff(self::COLUMNS, $header) !== []) {
                throw self::invalid('Use the StoreLift headers sku, title, action, description and url.');
            }
            $items = [];
            $record = 1;
            while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
                ++$record;
                if ($row === [null]) {
                    continue;
                }
                if (count($items) === 20) {
                    throw self::invalid('Import up to 20 listings at a time.');
                }
                if (count($row) !== count($header)) {
                    throw self::invalid("CSV record {$record} must have five fields.");
                }
                $fields = array_combine($header, array_map('trim', $row));
                ['sku' => $sku, 'title' => $title, 'action' => $action, 'description' => $description, 'url' => $url] = $fields;
                $url = CatalogImport::publicActionUrl($url);
                if (preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/D', $sku) !== 1 || isset($items[$sku])
                    || $title === '' || strlen($title) > 140 || $description === '' || strlen($description) > 600
                    || !isset(self::ACTIONS[$action]) || $url === null) {
                    throw self::invalid("Check CSV record {$record}. Use a unique lowercase SKU, a short title and description, a supported action and a public HTTPS link.");
                }
                $items[$sku] = [
                    'sku' => $sku,
                    'title' => $title,
                    'description' => $description,
                    'status' => $action,
                    'sourceUrl' => $url,
                    'detail' => '',
                    'actions' => [['label' => self::ACTIONS[$action], 'url' => $url, 'type' => $action]],
                ];
            }
            if ($items === []) {
                throw self::invalid('Add at least one listing to the CSV.');
            }
            return array_values($items);
        } finally {
            fclose($stream);
        }
    }

    private static function invalid(string $message): HttpError
    {
        return new HttpError(422, 'invalid_catalog_csv', $message);
    }
}
