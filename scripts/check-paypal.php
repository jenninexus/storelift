<?php

declare(strict_types=1);

use StoreLift\Config;
use StoreLift\HttpError;
use StoreLift\PayPalClient;

require dirname(__DIR__) . '/src/bootstrap.php';

try {
    $config = new Config(dirname(__DIR__));
    if ($config->clientId === '' || $config->clientSecret === '') {
        fwrite(STDERR, "StoreLift sandbox credentials are missing.\n");
        exit(1);
    }
    (new PayPalClient($config))->authenticate();
    echo "PayPal sandbox OAuth accepted the app credentials.\n";
} catch (HttpError $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, "StoreLift PayPal configuration could not be loaded.\n");
    exit(1);
}
