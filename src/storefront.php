<?php

use StoreLift\Catalog;
use StoreLift\StorefrontProfile;

$profile = StorefrontProfile::load(dirname(__DIR__));
$paid = ($receipt['state'] ?? '') === 'paid';
$retry = in_array($receipt['state'] ?? '', ['created', 'pending'], true);
$assets = [];
foreach (['store.css', 'app.js'] as $name) {
    $path = 'assets/' . $name;
    $assets[$name] = $path . '?v=' . substr(hash_file('sha256', dirname(__DIR__) . '/public/' . $path), 0, 12);
}
$sdk = 'https://www.paypal.com/sdk/js?' . http_build_query([
    'client-id' => $config->clientId,
    'currency' => Catalog::CURRENCY,
    'intent' => 'capture',
    'components' => 'buttons',
    'disable-funding' => 'credit,card,paylater,venmo',
]);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="storelift-csrf" content="<?= escape($csrf) ?>">
    <title><?= escape($profile['name']) ?> | Creator catalog and Sandbox checkout</title>
    <link rel="stylesheet" href="<?= escape($assets['store.css']) ?>">
    <?php if ($ready): ?>
        <script defer nonce="<?= escape($nonce) ?>" data-csp-nonce="<?= escape($nonce) ?>" src="<?= escape($sdk) ?>"></script>
    <?php endif; ?>
    <script defer src="<?= escape($assets['app.js']) ?>"></script>
</head>
<body>
<header class="masthead">
    <div class="brand"><span class="mark" aria-hidden="true">↗</span> <?= escape($profile['name']) ?></div>
    <span class="badge"><span aria-hidden="true">●</span> PayPal Sandbox</span>
</header>
<main>
    <section class="intro" aria-labelledby="title">
        <p class="eyebrow">Creator storefront</p>
        <h1 id="title">Bring your catalog to checkout.</h1>
        <p><?= escape($profile['tagline']) ?></p>
        <ol class="workflow" aria-label="StoreLift workflow">
            <li><span aria-hidden="true">01</span> Import</li>
            <li><span aria-hidden="true">02</span> Review &amp; approve</li>
            <li><span aria-hidden="true">03</span> Try Sandbox checkout</li>
        </ol>
    </section>
    <?php require __DIR__ . '/catalog-view.php'; ?>
    <div class="grid">
        <section class="panel kit" aria-labelledby="kit-heading">
            <div class="kit-art" aria-hidden="true"><span class="file-icon">ZIP</span><span class="kit-art-caption">CREATOR TOOLS · V1</span></div>
            <h2 id="kit-heading">Creator Demo Kit</h2>
            <p class="muted">A small starter pack for organizing a creator catalog and getting it ready for checkout.</p>
            <ul>
                <li>Catalog CSV template for free, support and paid listings</li>
                <li>Launch checklist for checkout and digital delivery</li>
                <li>Quick start guide</li>
            </ul>
        </section>
        <section id="checkout" class="panel checkout" aria-labelledby="checkout-heading"
                 data-sku="<?= escape(Catalog::SKU) ?>" data-attempt="<?= escape($attempt) ?>"
                 data-order="<?= escape($retry ? $receipt['orderId'] : '') ?>">
            <p class="eyebrow">Sandbox purchase</p>
            <h2 id="checkout-heading">Get the kit</h2>
            <p class="price">$<?= escape(Catalog::amount(Catalog::CENTS)) ?> <span><?= escape(Catalog::CURRENCY) ?> test amount</span></p>
            <p class="muted">Use a PayPal sandbox buyer account. The ZIP opens after payment is verified.</p>
            <div id="payment-actions" <?= $paid ? 'hidden' : '' ?>>
                <?php if ($ready): ?>
                    <div id="paypal-buttons" aria-label="PayPal checkout"></div>
                    <button id="verify-payment" class="secondary" type="button" <?= $retry ? '' : 'hidden' ?>>Check payment</button>
                <?php else: ?>
                    <p class="setup">Sandbox checkout is awaiting setup. See the StoreLift README for the setup steps.</p>
                <?php endif; ?>
            </div>
            <p id="payment-status" class="status" role="status" aria-live="polite"><?= ($receipt['state'] ?? '') === 'revoked'
                ? 'This payment was refunded or reversed. The download is unavailable.' : '' ?></p>
            <div id="receipt" class="receipt" <?= $paid ? '' : 'hidden' ?>>
                <strong>Sandbox payment verified</strong>
                <p id="capture-id"><?= $paid ? 'Capture ' . escape($receipt['captureId']) : '' ?></p>
                <a id="download" class="button" href="<?= $paid ? escape($receipt['downloadUrl']) : '#' ?>">Download Creator Demo Kit</a>
                <p class="muted small">Links last 10 minutes. Refresh this page in the same browser to get a new link.</p>
            </div>
        </section>
    </div>
    <footer>StoreLift · PayPal AI Hackathon prototype</footer>
</main>
</body>
</html>
