<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Tests\\')) {
        return;
    }
    $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, 6)) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
