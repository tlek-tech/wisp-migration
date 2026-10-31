#!/usr/bin/env php
<?php
define('APP_ROOT', __DIR__);

// Check if running inside project root with wisp-core
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/src/Console.php')) {
    require_once __DIR__ . '/src/Console.php';
    require_once __DIR__ . '/src/App.php';
    require_once __DIR__ . '/src/Request.php';
    require_once __DIR__ . '/src/Response.php';
    require_once __DIR__ . '/src/DB.php';
} elseif (file_exists(__DIR__ . '/../wisp-core/src/Console.php')) {
    require_once __DIR__ . '/../wisp-core/src/Console.php';
    require_once __DIR__ . '/../wisp-core/src/App.php';
    require_once __DIR__ . '/../wisp-core/src/Request.php';
    require_once __DIR__ . '/../wisp-core/src/Response.php';
    require_once __DIR__ . '/../wisp-core/src/DB.php';
}

// Require this plugin's entry point if not already loaded
if (file_exists(__DIR__ . '/plugins/migration/migration.php')) {
    require_once __DIR__ . '/plugins/migration/migration.php';
}

if (class_exists('Wisp\Console')) {
    $status = Wisp\Console::run($argv, __DIR__);
    exit($status);
} else {
    echo "Error: Wisp core Console class not found.\n";
    exit(1);
}
