<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Persistence;

use function array_map;
use function basename;
use function file_get_contents;
use function glob;
use function is_dir;

use PDO;
use PDOStatement;

use function preg_match;
use function sort;
use function sprintf;
use function str_ends_with;
use function substr;

use Throwable;

use function trim;

final readonly class Migrator
{
    public function __construct(
        private PDO $connection,
        private string $migrationDirectory,
    ) {}

    /**
     * @return list<string> Applied migration versions.
     */
    public function migrate(): array
    {
        $this->ensureMigrationTable();
        $applied = $this->appliedVersions();
        $newlyApplied = [];

        foreach ($this->upMigrations() as $version => $path) {
            if (isset($applied[$version])) {
                continue;
            }

            $sql = $this->readMigration($path);
            $this->connection->beginTransaction();

            try {
                $this->connection->exec($sql);
                $statement = $this->connection->prepare(
                    'INSERT INTO schema_migrations (version, applied_at) VALUES (:version, :applied_at)',
                );
                $statement->execute([
                    'version' => $version,
                    'applied_at' => gmdate('Y-m-d\TH:i:s\Z'),
                ]);
                $this->connection->commit();
                $newlyApplied[] = $version;
            } catch (Throwable $exception) {
                if ($this->connection->inTransaction()) {
                    $this->connection->rollBack();
                }

                throw new MigrationException(
                    sprintf('Migration %s failed and was rolled back.', $version),
                    previous: $exception,
                );
            }
        }

        return $newlyApplied;
    }

    public function rollbackLast(): ?string
    {
        $this->ensureMigrationTable();
        $statement = $this->query(
            'SELECT version FROM schema_migrations ORDER BY applied_at DESC, version DESC LIMIT 1',
        );
        $version = $statement->fetchColumn();
        $statement->closeCursor();

        if (false === $version) {
            return null;
        }

        $version = (string) $version;
        $path = $this->migrationDirectory . '/' . $version . '.down.sql';
        $sql = $this->readMigration($path);
        $this->connection->beginTransaction();

        try {
            $this->connection->exec($sql);
            $delete = $this->connection->prepare('DELETE FROM schema_migrations WHERE version = :version');
            $delete->execute(['version' => $version]);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw new MigrationException(
                sprintf('Rollback for migration %s failed and was rolled back.', $version),
                previous: $exception,
            );
        }

        return $version;
    }

    private function ensureMigrationTable(): void
    {
        $this->connection->exec(
            <<<'SQL'
                CREATE TABLE IF NOT EXISTS schema_migrations (
                    version TEXT PRIMARY KEY,
                    applied_at TEXT NOT NULL
                ) STRICT
                SQL,
        );
    }

    /**
     * @return array<string,true>
     */
    private function appliedVersions(): array
    {
        $statement = $this->query('SELECT version FROM schema_migrations');
        $versions = $statement->fetchAll(PDO::FETCH_COLUMN);

        return array_fill_keys(array_map('strval', $versions), true);
    }

    /**
     * @return array<string,string>
     */
    private function upMigrations(): array
    {
        if (!is_dir($this->migrationDirectory)) {
            throw new MigrationException('The migration directory does not exist.');
        }

        $paths = glob($this->migrationDirectory . '/*.up.sql');

        if (false === $paths) {
            throw new MigrationException('The migration directory could not be read.');
        }

        sort($paths);
        $migrations = [];

        foreach ($paths as $path) {
            $file = basename($path);

            if (1 !== preg_match('/^\d{3}_[a-z0-9_]+\.up\.sql$/D', $file)) {
                throw new MigrationException(sprintf('Invalid migration filename: %s.', $file));
            }

            $version = substr($file, 0, -strlen('.up.sql'));

            if (isset($migrations[$version])) {
                throw new MigrationException(sprintf('Duplicate migration version: %s.', $version));
            }

            $migrations[$version] = $path;
        }

        return $migrations;
    }

    private function readMigration(string $path): string
    {
        if (!str_ends_with($path, '.sql')) {
            throw new MigrationException('Migration paths must use the .sql extension.');
        }

        $sql = @file_get_contents($path);

        if (false === $sql || '' === trim($sql)) {
            throw new MigrationException(sprintf('Migration file is missing or empty: %s.', basename($path)));
        }

        return $sql;
    }

    private function query(string $sql): PDOStatement
    {
        $statement = $this->connection->query($sql);

        if (false === $statement) {
            throw new MigrationException('A migration metadata query could not be executed.');
        }

        return $statement;
    }
}
