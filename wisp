#!/usr/bin/env php
<?php
namespace Wisp;

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

// Bootstrap Wisp framework
define('WISP_CLI', true);
$baseDir = __DIR__;

// Load autoloader & env
if (file_exists($baseDir . '/vendor/autoload.php')) {
    require_once $baseDir . '/vendor/autoload.php';
    if (file_exists($baseDir . '/.env')) {
        $dotenv = \Dotenv\Dotenv::createImmutable($baseDir);
        $dotenv->load();
    }
} else {
    // Fallback PSR-4 and directory folder autoloader
    spl_autoload_register(function ($class) {
        $prefix = 'Wisp\\';
        $base_dir = __DIR__ . '/src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) === 0) {
            $relative_class = substr($class, $len);
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
        $dirs = [__DIR__ . '/services/', __DIR__ . '/middlewares/'];
        foreach ($dirs as $dir) {
            $file = $dir . str_replace('\\', '/', $class) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    });

    // Fallback simple .env parser
    if (file_exists(__DIR__ . '/.env')) {
        $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                if (preg_match('/^"(.*)"$/', $value, $matches) || preg_match('/^\'(.*)\'$/', $value, $matches)) {
                    $value = $matches[1];
                }
                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    putenv(sprintf('%s=%s', $name, $value));
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
}

// Register class aliases
class_alias('Wisp\Response', 'Response');
class_alias('Wisp\Request', 'Request');
class_alias('Wisp\DB', 'DB');

$app = new App();

// Load plugins
$pluginsDir = $baseDir . '/plugins';
if (is_dir($pluginsDir)) {
    foreach (scandir($pluginsDir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $fullPath = $pluginsDir . '/' . $item;
        if (is_dir($fullPath)) {
            $pluginFile = $fullPath . '/' . $item . '.php';
            if (file_exists($pluginFile)) {
                require_once $pluginFile;
            }
        } elseif (is_file($fullPath) && pathinfo($fullPath, PATHINFO_EXTENSION) === 'php') {
            require_once $fullPath;
        }
    }
}

// Load Services
if (file_exists($baseDir . '/config/services.php')) {
    require_once $baseDir . '/config/services.php';
}

// Handle Command Line Arguments
$args = $argv;
array_shift($args); // Remove script name

if (empty($args)) {
    printHelp();
    exit(0);
}

$command = $args[0];

function info($msg) { echo "\e[36m$msg\e[0m\n"; }
function success($msg) { echo "\e[32m$msg\e[0m\n"; }
function warn($msg) { echo "\e[33m$msg\e[0m\n"; }
function error($msg) { echo "\e[31m$msg\e[0m\n"; }

function printHelp() {
    success("Wisp Framework CLI Tool");
    echo "Usage: php wisp <command> [arguments]\n\n";
    echo "Available commands:\n";
    info("  migrate          "); echo "Run all pending database migrations\n";
    info("  migrate:rollback "); echo "Rollback the last batch of database migrations\n";
    info("  migrate:status   "); echo "Show the status of each migration\n";
    info("  migrate:create   "); echo "Create a new migration file skeleton (e.g. migrate:create create_users_table)\n";
}

try {
    $migrator = $app->migrator;
    
    switch ($command) {
        case 'migrate':
            info("Running migrations...");
            $executed = $migrator->runPending();
            if (empty($executed)) {
                warn("Nothing to migrate.");
            } else {
                foreach ($executed as $m) {
                    success("Migrated: {$m['name']} (Batch {$m['batch']})");
                }
                success("Database migrations completed successfully!");
            }
            break;

        case 'migrate:rollback':
            info("Rolling back migrations...");
            $rolled = $migrator->rollbackLastBatch();
            if (empty($rolled)) {
                warn("Nothing to rollback.");
            } else {
                foreach ($rolled as $m) {
                    success("Rolled back: {$m}");
                }
                success("Database rollbacks completed successfully!");
            }
            break;

        case 'migrate:status':
            $status = $migrator->getStatus();
            if (empty($status)) {
                warn("No migrations found.");
                break;
            }

            echo "\n+-------------------------------------------------------------+-----------+---------------------+\n";
            echo "| Migration File Name                                         | Status    | Applied At          |\n";
            echo "+-------------------------------------------------------------+-----------+---------------------+\n";
            foreach ($status as $s) {
                $name = str_pad($s['migration'], 59);
                $stateText = $s['applied'] ? "Ran (B#{$s['batch']})" : "Pending";
                $stateColored = $s['applied'] ? "\e[32m{$stateText}\e[0m" : "\e[33m{$stateText}\e[0m";
                
                // Pad state text to 9 characters, then replace the inner text with colored version
                $statePad = str_pad($stateText, 9);
                $stateOutput = str_replace($stateText, $stateColored, $statePad);
                
                $appliedAt = $s['applied_at'] ? $s['applied_at'] : '-';
                $appliedAtPad = str_pad($appliedAt, 19);

                echo "| {$name} | {$stateOutput} | {$appliedAtPad} |\n";
            }
            echo "+-------------------------------------------------------------+-----------+---------------------+\n\n";
            break;

        case 'migrate:create':
            $name = $args[1] ?? null;
            if (!$name) {
                error("Error: Migration name is required.");
                echo "Usage: php wisp migrate:create <name>\n";
                exit(1);
            }
            $filepath = $migrator->createMigrationFile($name);
            $filename = basename($filepath);
            success("Migration created successfully!");
            info("File: database/migrations/{$filename}");
            break;

        default:
            error("Unknown command: '$command'");
            printHelp();
            exit(1);
    }
} catch (\Exception $e) {
    error("Error executing command:");
    error($e->getMessage());
    exit(1);
}
