<?php

declare(strict_types=1);

use StoreLift\Config;

require dirname(__DIR__) . '/src/bootstrap.php';

if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "Enable the PHP zip extension to build the Creator Demo Kit.\n");
    exit(1);
}

$root = dirname(__DIR__);
$path = (new Config($root))->kitPath();
$temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
$zip = new ZipArchive();
if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    throw new RuntimeException('Could not create the demo ZIP.');
}

try {
    $files = ['README.md', 'catalog-template.csv', 'launch-checklist.md'];
    foreach ($files as $file) {
        $bytes = file_get_contents($root . '/kit/' . $file);
        if ($bytes === false || !$zip->addFromString($file, $bytes) || !$zip->setMtimeName($file, 1793923200)) {
            throw new RuntimeException('Could not package ' . $file);
        }
    }
    $license = file_get_contents($root . '/LICENSE');
    if ($license === false || !$zip->addFromString('LICENSE', $license) || !$zip->setMtimeName('LICENSE', 1793923200)
        || !$zip->close()) {
        throw new RuntimeException('Could not finish the demo ZIP.');
    }
    if (is_file($path)) {
        if (hash_file('sha256', $path) !== hash_file('sha256', $temporary)) {
            throw new RuntimeException('The existing v1 asset differs. Keep it for old orders and assign a new version.');
        }
    } elseif (!rename($temporary, $path)) {
        throw new RuntimeException('Could not save the demo ZIP.');
    }
    printf("Creator Demo Kit v1 | SHA-256 %s\n", hash_file('sha256', $path));
} finally {
    if (is_file($temporary)) {
        unlink($temporary);
    }
}
