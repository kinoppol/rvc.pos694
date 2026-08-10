<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

/**
 * Applies/rolls back versioned schema migrations. Used identically by
 * install.php (fresh install = run all pending) and by the admin
 * "Migrations" screen, so both paths can never drift apart.
 */
class MigrationRunner
{
    private PDO $db;
    private string $path;

    public function __construct(PDO $db, ?string $path = null)
    {
        $this->db = $db;
        $this->path = $path ?? APP_ROOT . '/database/migrations';
        $this->ensureMigrationsTable();
    }

    private function ensureMigrationsTable(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(191) NOT NULL UNIQUE,
            batch INT UNSIGNED NOT NULL,
            applied_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /** @return string[] filenames (without .php), sorted */
    public function allMigrationFiles(): array
    {
        $files = glob($this->path . '/*.php') ?: [];
        $names = array_map(fn($f) => basename($f, '.php'), $files);
        sort($names);
        return $names;
    }

    /** @return string[] */
    public function appliedMigrations(): array
    {
        $stmt = $this->db->query('SELECT migration FROM migrations ORDER BY id');
        return array_column($stmt->fetchAll(), 'migration');
    }

    /** @return string[] */
    public function pendingMigrations(): array
    {
        return array_values(array_diff($this->allMigrationFiles(), $this->appliedMigrations()));
    }

    public function nextBatchNumber(): int
    {
        $stmt = $this->db->query('SELECT COALESCE(MAX(batch), 0) AS b FROM migrations');
        return (int) $stmt->fetch()['b'] + 1;
    }

    /**
     * Runs every pending migration in a new batch.
     *
     * Note: migrations here are schema DDL (CREATE/DROP TABLE), and MySQL/
     * MariaDB implicitly commits the current transaction on every DDL
     * statement — so wrapping up()/down() in an explicit PDO transaction
     * cannot give real atomicity and just causes spurious "no active
     * transaction" errors on the follow-up commit(). We run each migration
     * un-wrapped instead; a failure stops the batch and is reported, and
     * whatever ran before it (already implicitly committed by MySQL) stays
     * applied — same effective behavior, without fighting the driver.
     *
     * @return array{applied: string[], error: ?string}
     */
    public function migrate(): array
    {
        $applied = [];
        $batch = $this->nextBatchNumber();
        foreach ($this->pendingMigrations() as $name) {
            $migration = $this->load($name);
            try {
                $migration->up($this->db);
                $stmt = $this->db->prepare('INSERT INTO migrations (migration, batch, applied_at) VALUES (?, ?, NOW())');
                $stmt->execute([$name, $batch]);
                $applied[] = $name;
            } catch (Throwable $e) {
                return ['applied' => $applied, 'error' => "$name: " . $e->getMessage()];
            }
        }
        return ['applied' => $applied, 'error' => null];
    }

    /**
     * Rolls back the most recent batch (used by the admin Migrations screen).
     *
     * @return array{rolled_back: string[], error: ?string}
     */
    public function rollbackLastBatch(): array
    {
        $stmt = $this->db->query('SELECT COALESCE(MAX(batch), 0) AS b FROM migrations');
        $batch = (int) $stmt->fetch()['b'];
        if ($batch === 0) {
            return ['rolled_back' => [], 'error' => null];
        }

        $stmt = $this->db->prepare('SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC');
        $stmt->execute([$batch]);
        $names = array_column($stmt->fetchAll(), 'migration');

        $rolledBack = [];
        foreach ($names as $name) {
            $migration = $this->load($name);
            try {
                $migration->down($this->db);
                $del = $this->db->prepare('DELETE FROM migrations WHERE migration = ?');
                $del->execute([$name]);
                $rolledBack[] = $name;
            } catch (Throwable $e) {
                return ['rolled_back' => $rolledBack, 'error' => "$name: " . $e->getMessage()];
            }
        }
        return ['rolled_back' => $rolledBack, 'error' => null];
    }

    private function load(string $name): object
    {
        $file = $this->path . '/' . $name . '.php';
        $migration = require $file;
        if (!is_object($migration) || !method_exists($migration, 'up') || !method_exists($migration, 'down')) {
            throw new \RuntimeException("Migration $name must return an object with up()/down() methods");
        }
        return $migration;
    }
}
