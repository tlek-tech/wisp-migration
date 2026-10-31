# wisp-migration

Database migration plugin and CLI tool for the [Wisp](../wisp) PHP micro-framework.

Adds migration versioning and batch tracking across MySQL, PostgreSQL, and SQLite, a service container binding for `\Wisp\Migrator` (`$app->migrator`), and a CLI tool (`wisp`) for running, rolling back, checking status, and scaffolding migrations.

## Requirements

Your Wisp core must support the plugin extension points this relies on:
- Container service registration via `$app->set('migrator', ...)`
- Database execution via `\Wisp\DB::execute()` and `\Wisp\DB::query()`
- The `plugins/` auto-loader in `public/index.php` (loads `plugins/<name>/<name>.php`)

## Install

1. Copy the `plugins/migration/` folder from this repo into your Wisp project's `plugins/` folder:
   ```
   your-wisp-project/
   └── plugins/
       └── migration/
           ├── migration.php
           └── src/
               ├── Migration.php
               └── Migrator.php
   ```
2. Copy the `wisp` CLI script into your project's root folder:
   ```
   your-wisp-project/
   └── wisp
   ```
3. Ensure your project's `.env` specifies a supported database connection:
   ```dotenv
   DB_CONNECTION=mysql    # mysql, pgsql, or sqlite
   ```
4. That's it — Wisp's plugin loader picks up `plugins/migration/migration.php` automatically on boot and registers `$app->migrator`.

## Usage

### CLI Commands

```bash
# Create a new migration skeleton in database/migrations/
php wisp migrate:create create_users_table

# Run all pending migrations
php wisp migrate

# Show status of all migrations
php wisp migrate:status

# Rollback the last batch of migrations
php wisp migrate:rollback
```

### Writing Migrations

Each migration file in `database/migrations/` extends `\Wisp\Migration`:

```php
use Wisp\Migration;
use Wisp\DB;

class CreateUsersTable extends Migration {
    public function up(): void {
        DB::execute("CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255))");
    }

    public function down(): void {
        DB::execute("DROP TABLE IF EXISTS users");
    }
}
```

### Programmatic Usage

```php
// Access the migrator service from the app container
$migrator = $app->migrator;

// Run pending migrations
$executed = $migrator->runPending();

// Rollback the last batch
$rolledBack = $migrator->rollbackLastBatch();
```

See `routes/migration-demo.php` in this repo for a runnable example (copy it into your project's
`routes/` folder if you want the `/migration-demo` web dashboard and API endpoints).

## License

MIT
