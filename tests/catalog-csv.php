<?php

declare(strict_types=1);

use StoreLift\CatalogCsv;
use StoreLift\HttpError;

require dirname(__DIR__) . '/src/bootstrap.php';

$example = file_get_contents(dirname(__DIR__) . '/examples/catalog.csv');
$items = CatalogCsv::parse("\xef\xbb\xbf" . str_replace("\n", "\r\n", $example));
if (count($items) !== 2 || $items[0]['status'] !== 'free' || $items[1]['actions'][0]['type'] !== 'link') {
    throw new RuntimeException('The public CSV template must import as two external listings.');
}

$header = "sku,title,action,description,url\n";
$valid = "tool,Tool,tip,\"First line, with a comma.\nSecond line.\",https://www.example.com/tool\n";
$items = CatalogCsv::parse($header . $valid);
if ($items[0]['description'] !== "First line, with a comma.\nSecond line."
    || $items[0]['actions'][0]['type'] !== 'tip') {
    throw new RuntimeException('Quoted CSV fields and action type must survive normalization.');
}

$invalid = [
    $header . $valid . $valid,
    $header . str_replace(',tip,', ',paid,', $valid),
    $header . str_replace('https://www.example.com/tool', 'javascript:alert(1)', $valid),
    $header . str_replace('https://www.example.com/tool', 'https://127.0.0.1/file', $valid),
    $header . str_replace('https://www.example.com/tool', 'https://a:b@www.example.com/file', $valid),
    $header . str_repeat('x', CatalogCsv::MAX_BYTES),
    file_get_contents(dirname(__DIR__) . '/kit/catalog-template.csv'),
];
foreach ($invalid as $csv) {
    try {
        CatalogCsv::parse($csv);
    } catch (HttpError $error) {
        if ($error->status === 422 && $error->error === 'invalid_catalog_csv') {
            continue;
        }
        throw $error;
    }
    throw new RuntimeException('Invalid catalog accepted.');
}

echo "Catalog CSV validation checks passed.\n";
