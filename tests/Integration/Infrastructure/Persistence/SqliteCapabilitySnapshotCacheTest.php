<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Integration\Infrastructure\Persistence;

use function dirname;

use EasyPrint\Domain\Printer\CapabilityCategory;
use EasyPrint\Domain\Printer\CapabilityChoice;
use EasyPrint\Domain\Printer\CapabilityOption;
use EasyPrint\Domain\Printer\CapabilitySnapshot;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Infrastructure\Persistence\CapabilitySnapshotCodec;
use EasyPrint\Infrastructure\Persistence\Migrator;
use EasyPrint\Infrastructure\Persistence\SqliteCapabilitySnapshotCache;
use EasyPrint\Infrastructure\Persistence\SqliteConnectionFactory;

use function hash;
use function is_file;

use PDO;
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class SqliteCapabilitySnapshotCacheTest extends TestCase
{
    private string $databasePath;
    private PDO $connection;
    private SqliteCapabilitySnapshotCache $cache;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/easy-print-capability-cache-' . uniqid('', true) . '.sqlite';
        $this->connection = SqliteConnectionFactory::create($this->databasePath);
        new Migrator($this->connection, dirname(__DIR__, 4) . '/database/migrations')->migrate();
        $this->cache = new SqliteCapabilitySnapshotCache($this->connection, new CapabilitySnapshotCodec());
    }

    protected function tearDown(): void
    {
        unset($this->cache, $this->connection);

        if (is_file($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    public function testItStoresValidatedSnapshotsByServerAndQueue(): void
    {
        $snapshot = $this->snapshot('QUEUE_A', 'first');
        $this->cache->save('primary', $snapshot, 100, 160);

        $cached = $this->cache->find('primary', 'QUEUE_A', 120);

        self::assertNotNull($cached);
        self::assertSame($snapshot->fingerprint, $cached->fingerprint);
        self::assertSame('PageSize', $cached->options[0]->technicalIdentifier);
        self::assertSame('A4', $cached->options[0]->defaultTechnicalIdentifier);
        self::assertNull($this->cache->find('secondary', 'QUEUE_A', 120));
        self::assertNull($this->cache->find('primary', 'QUEUE_B', 120));
    }

    public function testSavingAChangedFingerprintReplacesTheDriverSnapshot(): void
    {
        $this->cache->save('primary', $this->snapshot('QUEUE_A', 'first'), 100, 160);
        $replacement = $this->snapshot('QUEUE_A', 'changed-driver');
        $this->cache->save('primary', $replacement, 120, 180);

        self::assertSame(
            $replacement->fingerprint,
            $this->cache->find('primary', 'QUEUE_A', 140)?->fingerprint,
        );
    }

    public function testExpiredAndCorruptedEntriesAreInvalidated(): void
    {
        $this->cache->save('primary', $this->snapshot('EXPIRED', 'expired'), 100, 101);
        self::assertNull($this->cache->find('primary', 'EXPIRED', 101));

        $this->cache->save('primary', $this->snapshot('CORRUPTED', 'corrupted'), 100, 200);
        $this->connection->exec(
            "UPDATE capability_snapshots SET payload_json = '{}' WHERE queue_name = 'CORRUPTED'",
        );
        self::assertNull($this->cache->find('primary', 'CORRUPTED', 150));

        $statement = $this->connection->query('SELECT COUNT(*) FROM capability_snapshots');
        self::assertNotFalse($statement);
        $remaining = $statement->fetchColumn();
        self::assertSame(0, (int) $remaining);
    }

    private function snapshot(string $queueIdentifier, string $revision): CapabilitySnapshot
    {
        return new CapabilitySnapshot(
            queueIdentifier: $queueIdentifier,
            connectivity: CupsConnectivity::Available,
            options: [new CapabilityOption(
                technicalIdentifier: 'PageSize',
                driverLabel: 'Media Size',
                category: CapabilityCategory::MediaSize,
                choices: [new CapabilityChoice('A4'), new CapabilityChoice('Letter')],
                defaultTechnicalIdentifier: 'A4',
            )],
            fingerprint: hash('sha256', $revision),
        );
    }
}
