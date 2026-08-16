<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Integration\Infrastructure\Health;

use function dirname;

use EasyPrint\Application\Health\HealthStatus;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Domain\Printer\QueueSnapshot;
use EasyPrint\Infrastructure\Health\OperationalReadinessProbe;
use EasyPrint\Infrastructure\Persistence\Migrator;
use EasyPrint\Infrastructure\Persistence\SqliteConnectionFactory;
use EasyPrint\Tests\Support\FakeQueueDiscovery;

use function mkdir;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class OperationalReadinessProbeTest extends TestCase
{
    private string $directory;
    private string $databasePath;
    private string $temporaryPath;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/easy-print-readiness-' . uniqid('', true);
        $this->databasePath = $this->directory . '/database/easy-print.sqlite';
        $this->temporaryPath = $this->directory . '/temporary';
        mkdir($this->directory . '/database', recursive: true);
        mkdir($this->temporaryPath);
        new Migrator(
            SqliteConnectionFactory::create($this->databasePath),
            dirname(__DIR__, 4) . '/database/migrations',
        )->migrate();
    }

    protected function tearDown(): void
    {
        if (is_file($this->databasePath)) {
            unlink($this->databasePath);
        }

        if (is_dir($this->temporaryPath)) {
            rmdir($this->temporaryPath);
        }
        rmdir($this->directory . '/database');
        rmdir($this->directory);
    }

    public function testItChecksWritableStorageMigratedSqliteAndCupsSeparately(): void
    {
        $report = $this->probe(CupsConnectivity::Available)->check();

        self::assertTrue($report->storageReady);
        self::assertTrue($report->databaseReady);
        self::assertSame(CupsConnectivity::Available, $report->cupsConnectivity);
        self::assertSame(HealthStatus::Ok, $report->status());
    }

    public function testACupsOutageIsVisibleButDoesNotMarkLocalStateUnavailable(): void
    {
        $report = $this->probe(CupsConnectivity::Unauthorized)->check();

        self::assertTrue($report->storageReady);
        self::assertTrue($report->databaseReady);
        self::assertSame(CupsConnectivity::Unauthorized, $report->cupsConnectivity);
        self::assertSame(HealthStatus::Degraded, $report->status());
    }

    public function testAnUnmigratedDatabaseIsUnavailable(): void
    {
        unlink($this->databasePath);

        $report = $this->probe(CupsConnectivity::Available)->check();

        self::assertFalse($report->databaseReady);
        self::assertFileDoesNotExist($this->databasePath);
        self::assertSame(HealthStatus::Unavailable, $report->status());
    }

    public function testMissingTemporaryStorageIsUnavailable(): void
    {
        rmdir($this->temporaryPath);

        $report = $this->probe(CupsConnectivity::Available)->check();

        self::assertFalse($report->storageReady);
        self::assertTrue($report->databaseReady);
        self::assertSame(HealthStatus::Unavailable, $report->status());
    }

    private function probe(CupsConnectivity $connectivity): OperationalReadinessProbe
    {
        $snapshot = CupsConnectivity::Available === $connectivity
            ? new QueueSnapshot(CupsConnectivity::Available)
            : QueueSnapshot::failed($connectivity);

        return new OperationalReadinessProbe(
            databasePath: $this->databasePath,
            temporaryPath: $this->temporaryPath,
            queueDiscovery: new FakeQueueDiscovery($snapshot),
            logger: new NullLogger(),
        );
    }
}
