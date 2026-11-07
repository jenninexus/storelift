<?php

declare(strict_types=1);

use StoreLift\CatalogAi;
use StoreLift\HttpError;

require dirname(__DIR__) . '/src/bootstrap.php';

function providerResponse(string $content, string $finishReason = 'stop'): string
{
    return json_encode([
        'id' => 'fixture-completion',
        'model' => 'fixture-model',
        'choices' => [['finish_reason' => $finishReason, 'message' => ['content' => $content]]],
    ], JSON_THROW_ON_ERROR);
}

function expectError(string $expected, callable $run): void
{
    try {
        $run();
    } catch (HttpError $error) {
        if ($error->error === $expected) {
            return;
        }
        throw $error;
    }
    throw new RuntimeException('Expected ' . $expected);
}

$items = [
    ['sku' => 'tool', 'actions' => [['type' => 'free'], ['type' => 'tip']]],
    ['sku' => 'membership', 'actions' => []],
];
$tool = [
    'sku' => 'tool',
    'description' => 'Organize a creator catalog.',
    'category' => 'Creator tools',
    'actionTypes' => ['free', 'tip'],
    'warnings' => ['Check whether a sample is available.'],
];
$membership = [
    'sku' => 'membership',
    'description' => 'Support the creator through a membership.',
    'category' => 'Membership',
    'actionTypes' => [],
    'warnings' => [],
];
$validContent = json_encode(['suggestions' => [$tool, $membership]], JSON_THROW_ON_ERROR);
$result = CatalogAi::decode(providerResponse($validContent), $items);
if ($result['requestId'] !== 'fixture-completion' || $result['model'] !== 'fixture-model'
    || $result['suggestions']['tool']['actionTypes'] !== ['free', 'tip']
    || $result['suggestions']['membership']['category'] !== 'Membership') {
    throw new RuntimeException('AI review did not retain the expected suggestions and provenance.');
}

$invalidDrafts = [
    [$tool, $tool],
    [$tool, array_replace($membership, ['sku' => 'unknown'])],
    [$tool + ['price' => 5], $membership],
    [array_replace($tool, ['description' => '<img src=x>']), $membership],
    [array_replace($tool, ['description' => 'Buy at https://example.com/']), $membership],
    [array_replace($tool, ['actionTypes' => ['free', 'paid']]), $membership],
    [array_replace($tool, ['actionTypes' => ['free']]), $membership],
    [$tool, array_replace($membership, ['warnings' => (object) []])],
];
foreach ($invalidDrafts as $draft) {
    $response = providerResponse(json_encode(['suggestions' => $draft], JSON_THROW_ON_ERROR));
    expectError('ai_invalid_output', static fn () => CatalogAi::decode($response, $items));
}
foreach (['no JSON', '"string"', '[]', '{}'] as $content) {
    expectError('ai_invalid_output', static fn () => CatalogAi::decode(providerResponse($content), $items));
}
expectError('ai_invalid_output', static fn () => CatalogAi::decode(providerResponse($validContent, 'length'), $items));
expectError('ai_setup_required', static fn () => CatalogAi::suggest($items, 'http://127.0.0.1/', 'model', 'fixture-key'));
expectError('ai_setup_required', static fn () => CatalogAi::suggest($items, '', '', ''));

echo "Catalog AI validation checks passed.\n";
