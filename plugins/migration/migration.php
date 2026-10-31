<?php
namespace Wisp;

require_once __DIR__ . '/src/Migration.php';
require_once __DIR__ . '/src/Migrator.php';

// Register migrator into service container
global $app;
$appInstance = $app ?? ($GLOBALS['app'] ?? null);
if ($appInstance !== null && method_exists($appInstance, 'set')) {
    $appInstance->set('migrator', function($c) {
        return new Migrator();
    });
}

// Register CLI commands if running under Wisp Console
if (class_exists('Wisp\Console')) {
    Console::register('migrate', function(array $args, App $app) {
        Console::info("Running migrations...");
        $migrator = $app->migrator;
        $executed = $migrator->runPending();
        if (empty($executed)) {
            Console::warn("Nothing to migrate.");
        } else {
            foreach ($executed as $m) {
                Console::success("Migrated: {$m['name']} (Batch {$m['batch']})");
            }
            Console::success("Database migrations completed successfully!");
        }
        return 0;
    }, 'Run all pending database migrations');

    Console::register('migrate:rollback', function(array $args, App $app) {
        Console::info("Rolling back migrations...");
        $migrator = $app->migrator;
        $rolled = $migrator->rollbackLastBatch();
        if (empty($rolled)) {
            Console::warn("Nothing to rollback.");
        } else {
            foreach ($rolled as $m) {
                Console::success("Rolled back: {$m}");
            }
            Console::success("Database rollbacks completed successfully!");
        }
        return 0;
    }, 'Rollback the last batch of database migrations');

    Console::register('migrate:status', function(array $args, App $app) {
        $migrator = $app->migrator;
        $status = $migrator->getStatus();
        if (empty($status)) {
            Console::warn("No migrations found.");
            return 0;
        }

        $headers = ['Migration File Name', 'Status', 'Applied At'];
        $rows = [];
        foreach ($status as $s) {
            $stateText = $s['applied'] ? Console::color("Ran (B#{$s['batch']})", 'green') : Console::color("Pending", 'yellow');
            $rows[] = [
                $s['migration'],
                $stateText,
                $s['applied_at'] ?: '-'
            ];
        }
        Console::table($headers, $rows);
        return 0;
    }, 'Show the status of each migration');

    Console::register('migrate:create', function(array $args, App $app) {
        $name = $args[0] ?? null;
        if (!$name) {
            Console::error("Missing migration name. Example: php wisp migrate:create create_users_table");
            return 1;
        }
        $migrator = $app->migrator;
        $filepath = $migrator->createMigrationFile($name);
        $filename = basename($filepath);
        Console::success("Migration created successfully: database/migrations/{$filename}");
        return 0;
    }, 'Create a new migration file skeleton');
}
