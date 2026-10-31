<?php
namespace Wisp;

require_once __DIR__ . '/src/Migration.php';
require_once __DIR__ . '/src/Migrator.php';

global $app;
if (isset($app)) {
    $app->set('migrator', function($c) {
        return new Migrator();
    });
}
