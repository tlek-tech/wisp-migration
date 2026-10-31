<?php
namespace Wisp;

class Migrator {
    protected string $migrationsPath;

    public function __construct() {
        $this->migrationsPath = realpath(__DIR__ . '/../../../') . '/database/migrations';
    }

    /**
     * Get the absolute path to the migrations directory.
     */
    public function getMigrationsPath(): string {
        return $this->migrationsPath;
    }

    /**
     * Ensure migrations directory exists.
     */
    protected function ensureMigrationsDirectoryExists(): void {
        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0777, true);
        }
    }

    /**
     * Ensure the migrations tracker table exists in the database.
     */
    public function ensureMigrationsTableExists(): void {
        $driver = strtolower($_ENV['DB_CONNECTION'] ?? 'mysql');
        
        switch ($driver) {
            case 'sqlite':
                $sql = "CREATE TABLE IF NOT EXISTS migrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration VARCHAR(255) NOT NULL,
                    batch INTEGER NOT NULL,
                    applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )";
                break;
            case 'pgsql':
            case 'postgres':
            case 'postgresql':
                $sql = "CREATE TABLE IF NOT EXISTS migrations (
                    id SERIAL PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL,
                    batch INTEGER NOT NULL,
                    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                break;
            case 'mysql':
            default:
                $sql = "CREATE TABLE IF NOT EXISTS migrations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL,
                    batch INT NOT NULL,
                    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                break;
        }

        DB::execute($sql);
    }

    /**
     * Get all applied migrations from database.
     */
    public function getAppliedMigrations(): array {
        $this->ensureMigrationsTableExists();
        return DB::query("SELECT migration, batch, applied_at FROM migrations ORDER BY id ASC");
    }

    /**
     * Get all migration files from the filesystem.
     */
    public function getMigrationFiles(): array {
        $this->ensureMigrationsDirectoryExists();
        $files = glob($this->migrationsPath . '/*.php');
        if ($files === false) {
            return [];
        }

        $migrations = [];
        foreach ($files as $file) {
            $filename = basename($file, '.php');
            // Ensure matches typical migration pattern
            if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_.+$/', $filename)) {
                $migrations[$filename] = $file;
            }
        }
        
        // Sort migrations by timestamp/name
        ksort($migrations);
        return $migrations;
    }

    /**
     * Get pending migrations that have not been executed yet.
     */
    public function getPendingMigrations(): array {
        $applied = array_column($this->getAppliedMigrations(), 'migration');
        $files = $this->getMigrationFiles();

        $pending = [];
        foreach ($files as $name => $path) {
            if (!in_array($name, $applied)) {
                $pending[$name] = $path;
            }
        }
        return $pending;
    }

    /**
     * Get the next execution batch number.
     */
    public function getNextBatchNumber(): int {
        $this->ensureMigrationsTableExists();
        $result = DB::query("SELECT MAX(batch) as max_batch FROM migrations");
        $max = $result[0]['max_batch'] ?? 0;
        return (int)$max + 1;
    }

    /**
     * Run all pending migrations.
     */
    public function runPending(): array {
        $this->ensureMigrationsTableExists();
        $pending = $this->getPendingMigrations();
        if (empty($pending)) {
            return [];
        }

        $batch = $this->getNextBatchNumber();
        $executed = [];

        foreach ($pending as $name => $path) {
            require_once $path;
            $className = $this->resolveClassName($name);
            
            if (!class_exists($className)) {
                throw new \Exception("Migration class '$className' not found in file '$path'");
            }

            $migration = new $className();
            $migration->up();

            DB::execute(
                "INSERT INTO migrations (migration, batch) VALUES (?, ?)",
                [$name, $batch]
            );

            $executed[] = [
                'name' => $name,
                'batch' => $batch
            ];
        }

        return $executed;
    }

    /**
     * Rollback the last batch of migrations.
     */
    public function rollbackLastBatch(): array {
        $this->ensureMigrationsTableExists();
        
        // Find the last batch number
        $result = DB::query("SELECT MAX(batch) as max_batch FROM migrations");
        $lastBatch = $result[0]['max_batch'] ?? null;
        
        if ($lastBatch === null || (int)$lastBatch === 0) {
            return [];
        }

        // Fetch migrations in the last batch (rollback in reverse order of creation/id)
        $migrations = DB::query(
            "SELECT id, migration FROM migrations WHERE batch = ? ORDER BY id DESC",
            [(int)$lastBatch]
        );

        $files = $this->getMigrationFiles();
        $rolledBack = [];

        foreach ($migrations as $row) {
            $name = $row['migration'];
            
            if (isset($files[$name])) {
                require_once $files[$name];
                $className = $this->resolveClassName($name);
                
                if (class_exists($className)) {
                    $migration = new $className();
                    $migration->down();
                }
            }
            
            // Delete from database regardless of whether file exists (to keep state in sync)
            DB::execute("DELETE FROM migrations WHERE id = ?", [$row['id']]);
            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    /**
     * Create a new migration file stub.
     */
    public function createMigrationFile(string $name): string {
        $this->ensureMigrationsDirectoryExists();
        
        $timestamp = date('Y_m_d_His');
        $normalizedName = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $name));
        $normalizedName = trim(preg_replace('/_+/', '_', $normalizedName), '_');
        
        $filename = "{$timestamp}_{$normalizedName}";
        $filepath = $this->migrationsPath . '/' . $filename . '.php';
        
        $className = $this->resolveClassName($filename);
        
        $stub = <<<PHP
<?php
use Wisp\Migration;
use Wisp\DB;

class {$className} extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        // Write your migration query here
        // Example: DB::execute("CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        // Reverse your migration query here
        // Example: DB::execute("DROP TABLE IF EXISTS users");
    }
}
PHP;

        file_put_contents($filepath, $stub);
        return $filepath;
    }

    /**
     * Get status of all migrations.
     */
    public function getStatus(): array {
        $applied = [];
        foreach ($this->getAppliedMigrations() as $row) {
            $applied[$row['migration']] = [
                'batch' => $row['batch'],
                'applied_at' => $row['applied_at']
            ];
        }

        $files = $this->getMigrationFiles();
        $status = [];

        foreach ($files as $name => $path) {
            $isApplied = isset($applied[$name]);
            $status[] = [
                'migration' => $name,
                'applied' => $isApplied,
                'batch' => $isApplied ? $applied[$name]['batch'] : null,
                'applied_at' => $isApplied ? $applied[$name]['applied_at'] : null
            ];
        }

        return $status;
    }

    /**
     * Resolve the class name from a migration filename.
     * e.g. 2026_07_19_120000_create_users_table -> CreateUsersTable
     */
    protected function resolveClassName(string $filename): string {
        // Match name after the timestamp (15 chars e.g. YYYY_MM_DD_HHMMSS_)
        if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_(.*)$/', $filename, $matches)) {
            $name = $matches[1];
        } else {
            $name = $filename;
        }
        
        $words = str_replace('_', ' ', $name);
        $words = ucwords($words);
        return str_replace(' ', '', $words);
    }
}
