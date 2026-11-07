<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    if (preg_match('/^StoreLift\\\\([A-Za-z][A-Za-z0-9]*)$/D', $class, $matches) === 1) {
        $file = __DIR__ . '/' . $matches[1] . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
