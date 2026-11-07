<?php

declare(strict_types=1);

use StoreLift\CatalogImport;
use StoreLift\HttpError;

require dirname(__DIR__) . '/src/bootstrap.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$card = <<<'HTML'
<div class="card product-widget" data-sku="tool" data-status="free" data-product-detail="product-detail-tool">
    <h2>Résumé Tool</h2>
    <p>Download the free kit &amp; learn.</p>
    <div><a href="/tags">Not an action</a></div>
    <div class="product-widget-actions">
        <a href="https://github.com/jenninexus/pdf-designer">Clone on GitHub</a>
        <a href="javascript:alert(1)">Unsafe script</a>
        <a href="//other.net/path">Protocol relative</a>
        <a href="https://paypal.me@other.net/path">Misleading user info</a>
        <a href="https://127.0.0.1/path">Private address</a>
        <a href="https://router.home.arpa/path">Local host</a>
        <a href="https://jenninexus.com:8443/path">Unexpected port</a>
    </div>
</div>
HTML;
$additional = <<<'HTML'
<div class="product-detail-modal" id="product-detail-tool">
    <p class="lead">Open-source toolkit.</p>
    <div class="product-detail-modal__actions">
        <a href="https://github.com/jenninexus/pdf-designer">Clone</a>
        <a href="/blog/tool">Read the post</a>
    </div>
</div>
<div class="product-widget" data-sku="membership" data-status="membership">
    <h2>Membership</h2>
    <div class="product-widget-actions">
        <a href="/patreon">Become a Patron</a>
        <a href="https://paypal.me/jenninexus">Tip $3 or $5</a>
    </div>
</div>
HTML;

[$tool, $membership] = CatalogImport::parse($card . $additional);
check($tool['title'] === 'Résumé Tool' && $tool['description'] === 'Download the free kit & learn.', 'UTF-8 or entity loss');
check($tool['sourceUrl'] === CatalogImport::SOURCE_URL . '#tool' && $tool['detail'] === 'Open-source toolkit.', 'Source evidence loss');
check($tool['actions'] === [
    ['url' => 'https://github.com/jenninexus/pdf-designer', 'label' => 'Clone on GitHub', 'type' => 'free'],
    ['url' => 'https://jenninexus.com/blog/tool', 'label' => 'Read the post', 'type' => 'link'],
], 'Action filtering, ordering or normalization failed');
check($membership['status'] === 'membership' && array_column($membership['actions'], 'type') === ['membership', 'tip'],
    'Membership and support actions need separate source types');

foreach (['https://jenninexus.com/products?other=1', 'https://jenninexus.com/products/', 'https://127.0.0.1/products'] as $url) {
    try {
        CatalogImport::fetch($url);
        throw new RuntimeException('Allowed an unsupported source URL');
    } catch (HttpError $error) {
        check($error->status === 422 && $error->error === 'unsupported_catalog', 'Wrong unsupported source error');
    }
}

foreach (['<main>No product cards.</main>', $card . $card, str_repeat('x', 512_001)] as $html) {
    try {
        CatalogImport::parse($html);
        throw new RuntimeException('Accepted an unrecognized catalog');
    } catch (HttpError $error) {
        check($error->status === 502 && $error->error === 'catalog_unrecognized', 'Wrong source format error');
    }
}

echo "Catalog importer tests passed.\n";
