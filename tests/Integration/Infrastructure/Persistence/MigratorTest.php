<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Integration\Infrastructure\Persistence;

use function dirname;

use EasyPrint\Infrastructure\Persistence\MigrationException;
use EasyPrint\Infrastructure\Persistence\Migrator;
use EasyPrint\Infrastructure\Persistence\SqliteConnectionFactory;

use function file_put_contents;
use function is_dir;
use function mkdir;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class MigratorTest extends TestCase
{
    private string $databasePath;
    private PDO $connection;
    private string $migrations;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/easy-print-' . uniqid('', true) . '.sqlite';
        $this->connection = SqliteConnectionFactory::create($this->databasePath);
        $this->migrations = dirname(__DIR__, 4) . '/database/migrations';
    }

    protected function tearDown(): void
    {
        unset($this->connection);

        if (is_file($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    public function testItAppliesTheMetadataSchemaToAnEmptyDatabase(): void
    {
        $migrator = new Migrator($this->connection, $this->migrations);

        self::assertSame(['001_initial_metadata'], $migrator->migrate());
        self::assertSame([], $migrator->migrate());

        $tables = $this
            ->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name")
            ->fetchAll(PDO::FETCH_COLUMN);

        self::assertContains('print_jobs', $tables);
        self::assertContains('job_events', $tables);
        self::assertContains('operational_errors', $tables);
        self::assertContains('schema_migrations', $tables);
    }

    public function testThePrintJobSchemaContainsMetadataButNoDocumentLocationOrBytes(): void
    {
        new Migrator($this->connection, $this->migrations)->migrate();
        $columns = $this->query('PRAGMA table_info(print_jobs)')->fetchAll(PDO::FETCH_ASSOC);
        $names = array_column($columns, 'name');

        self::assertContains('queue_name', $names);
        self::assertContains('cups_job_id', $names);
        self::assertContains('options_json', $names);
        self::assertContains('retained_until', $names);
        self::assertNotContains('document', $names);
        self::assertNotContains('document_path', $names);
        self::assertNotContains('document_bytes', $names);
    }

    public function testItRollsBackTheLatestMigrationTransactionally(): void
    {
        $migrator = new Migrator($this->connection, $this->migrations);
        $migrator->migrate();

        self::assertSame('001_initial_metadata', $migrator->rollbackLast());
        self::assertFalse($this
            ->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'print_jobs'")
            ->fetchColumn());
        self::assertNull($migrator->rollbackLast());
    }

    public function testAFailedMigrationDoesNotRecordItsVersionOrPartialSchema(): void
    {
        $directory = sys_get_temp_dir() . '/easy-print-migrations-' . uniqid('', true);
        mkdir($directory);
        file_put_contents(
            $directory . '/001_broken.up.sql',
            'CREATE TABLE should_not_remain (id INTEGER); THIS IS NOT SQL;',
        );

        $migrator = new Migrator($this->connection, $directory);

        try {
            $migrator->migrate();
            self::fail('Expected the invalid migration to fail.');
        } catch (MigrationException $exception) {
            self::assertStringContainsString('rolled back', $exception->getMessage());
        } finally {
            unlink($directory . '/001_broken.up.sql');
            rmdir($directory);
        }

        self::assertFalse($this
            ->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'should_not_remain'")
            ->fetchColumn());
        self::assertSame(0, (int) $this->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn());
    }

    private function query(string $sql): PDOStatement
    {
        $statement = $this->connection->query($sql);

        if (false === $statement) {
            throw new RuntimeException('The test query could not be executed.');
        }

        return $statement;
    }
}
