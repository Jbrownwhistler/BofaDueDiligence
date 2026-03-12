<?php
/**
 * BofaDueDiligence - Autoloader
 */
spl_autoload_register(function ($class) {
    $dirs = [
        __DIR__ . '/Controllers/',
        __DIR__ . '/Models/',
        __DIR__ . '/Helpers/',
    ];
    foreach ($dirs as $dir) {
        $file = $dir . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
