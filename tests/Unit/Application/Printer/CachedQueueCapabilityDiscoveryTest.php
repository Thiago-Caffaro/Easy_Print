<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Unit\Application\Printer;

use EasyPrint\Application\Printer\CachedQueueCapabilityDiscovery;
use EasyPrint\Domain\Printer\CapabilitySnapshot;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Infrastructure\Persistence\CapabilitySnapshotCodec;
use EasyPrint\Infrastructure\Persistence\Migrator;
use EasyPrint\Infrastructure\Persistence\SqliteCapabilitySnapshotCache;
use EasyPrint\Infrastructure\Persistence\SqliteConnectionFactory;
use EasyPrint\Tests\Support\FakeQueueCapabilityDiscovery;

use function hash;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CachedQueueCapabilityDiscoveryTest extends TestCase
{
    public function testItUsesTheShortCacheAndRefreshesAfterExpiry(): void
    {
        $connection = SqliteConnectionFactory::create(':memory:');
        new Migrator($connection, dirname(__DIR__, 4) . '/database/migrations')->migrate();
        $source = new FakeQueueCapabilityDiscovery([
            $this->snapshot('QUEUE_A', 'driver-one'),
            $this->snapshot('QUEUE_A', 'driver-two'),
        ]);
        $now = 100;
        $discovery = new CachedQueueCapabilityDiscovery(
            source: $source,
            cache: new SqliteCapabilitySnapshotCache($connection, new CapabilitySnapshotCodec()),
            serverKey: 'primary',
            ttlSeconds: 60,
            clock: static function () use (&$now): int {
                return $now;
            },
        );

        $first = $discovery->discover('QUEUE_A');
        $cached = $discovery->discover('QUEUE_A');
        $now = 160;
        $refreshed = $discovery->discover('QUEUE_A');

        self::assertSame($first->fingerprint, $cached->fingerprint);
        self::assertNotSame($first->fingerprint, $refreshed->fingerprint);
        self::assertSame(['QUEUE_A', 'QUEUE_A'], $source->calls);
    }

    public function testQueueChangesCannotReuseAnotherQueuesSnapshot(): void
    {
        $connection = SqliteConnectionFactory::create(':memory:');
        new Migrator($connection, dirname(__DIR__, 4) . '/database/migrations')->migrate();
        $source = new FakeQueueCapabilityDiscovery([
            $this->snapshot('QUEUE_A', 'a'),
            $this->snapshot('QUEUE_B', 'b'),
        ]);
        $discovery = new CachedQueueCapabilityDiscovery(
            $source,
            new SqliteCapabilitySnapshotCache($connection, new CapabilitySnapshotCodec()),
            'primary',
            60,
            static fn(): int => 100,
        );

        self::assertSame('QUEUE_A', $discovery->discover('QUEUE_A')->queueIdentifier);
        self::assertSame('QUEUE_B', $discovery->discover('QUEUE_B')->queueIdentifier);
        self::assertSame(['QUEUE_A', 'QUEUE_B'], $source->calls);
    }

    public function testZeroTtlAlwaysUsesTheSourceAndDoesNotReuseStaleData(): void
    {
        $connection = SqliteConnectionFactory::create(':memory:');
        new Migrator($connection, dirname(__DIR__, 4) . '/database/migrations')->migrate();
        $source = new FakeQueueCapabilityDiscovery([
            CapabilitySnapshot::failed('QUEUE_A', CupsConnectivity::Unavailable),
            CapabilitySnapshot::failed('QUEUE_A', CupsConnectivity::TimedOut),
        ]);
        $discovery = new CachedQueueCapabilityDiscovery(
            $source,
            new SqliteCapabilitySnapshotCache($connection, new CapabilitySnapshotCodec()),
            'primary',
            0,
        );

        self::assertSame(CupsConnectivity::Unavailable, $discovery->discover('QUEUE_A')->connectivity);
        self::assertSame(CupsConnectivity::TimedOut, $discovery->discover('QUEUE_A')->connectivity);
        self::assertSame(['QUEUE_A', 'QUEUE_A'], $source->calls);
    }

    public function testItRejectsANegativeTtl(): void
    {
        $connection = SqliteConnectionFactory::create(':memory:');
        new Migrator($connection, dirname(__DIR__, 4) . '/database/migrations')->migrate();
        $this->expectException(InvalidArgumentException::class);

        new CachedQueueCapabilityDiscovery(
            new FakeQueueCapabilityDiscovery([]),
            new SqliteCapabilitySnapshotCache($connection, new CapabilitySnapshotCodec()),
            'primary',
            -1,
        );
    }

    private function snapshot(string $queueIdentifier, string $revision): CapabilitySnapshot
    {
        return new CapabilitySnapshot(
            queueIdentifier: $queueIdentifier,
            connectivity: CupsConnectivity::Available,
            fingerprint: hash('sha256', $revision),
        );
    }
}
