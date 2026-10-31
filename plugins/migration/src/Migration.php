<?php
namespace Wisp;

abstract class Migration {
    /**
     * Run the migrations.
     */
    abstract public function up(): void;

    /**
     * Reverse the migrations.
     */
    abstract public function down(): void;

    /**
     * Helper to get database connection driver
     */
    protected function getDriver(): string {
        return strtolower($_ENV['DB_CONNECTION'] ?? 'mysql');
    }
}
