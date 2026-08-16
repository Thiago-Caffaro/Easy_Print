<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Integration\Infrastructure\Persistence;

use function dirname;

use EasyPrint\Infrastructure\Persistence\Migrator;
use EasyPrint\Infrastructure\Persistence\SqliteConnectionFactory;
use EasyPrint\Infrastructure\Persistence\SqlitePrintHistoryReader;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class SqlitePrintHistoryReaderTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'easy-print-history-');
        self::assertNotFalse($path);
        $this->databasePath = $path;
        $connection = SqliteConnectionFactory::create($path);
        new Migrator($connection, dirname(__DIR__, 4) . '/database/migrations')->migrate();
        $connection->exec(<<<'SQL'
            INSERT INTO print_jobs (
                id, correlation_id, cups_server_key, queue_name, cups_job_id, original_name,
                detected_media_type, byte_size, copies, page_range, options_json, state,
                submitted_at, updated_at, finished_at, retained_until
            ) VALUES
            ('new', 'correlation-new', 'server', 'QUEUE_A', 9, 'new.pdf', 'application/pdf',
             2048, 2, '1-3', '{"version":1,"values":{"PageSize":"A4"}}', 'completed',
             '2026-08-14T10:00:00Z', '2026-08-14T10:01:00Z', '2026-08-14T10:01:00Z', '2026-11-14T10:00:00Z'),
            ('old', 'correlation-old', 'server', 'QUEUE_B', NULL, 'old.png', 'image/png',
             1024, 1, NULL, '{"ColorModel":"RGB"}', 'failed',
             '2026-08-13T10:00:00Z', '2026-08-13T10:01:00Z', '2026-08-13T10:01:00Z', '2026-11-13T10:00:00Z')
            SQL);
    }

    protected function tearDown(): void
    {
        unlink($this->databasePath);
    }

    public function testItReadsNewestFirstAndSupportsVersionedAndLegacyOptions(): void
    {
        $reader = new SqlitePrintHistoryReader(
            $this->databasePath,
            new NullLogger(),
        );
        $page = $reader->readPage(1, 1);

        self::assertTrue($page->available);
        self::assertSame(2, $page->totalItems);
        self::assertSame(2, $page->totalPages());
        self::assertSame('new', $page->entries[0]->id);
        self::assertSame(['PageSize' => 'A4'], $page->entries[0]->selectedOptions);

        $second = $reader->readPage(2, 1);
        self::assertSame('old', $second->entries[0]->id);
        self::assertSame(['ColorModel' => 'RGB'], $second->entries[0]->selectedOptions);
    }

    public function testItReturnsAnUnavailablePageForADatabaseFailure(): void
    {
        $reader = new SqlitePrintHistoryReader(
            $this->databasePath . '/missing.sqlite',
            new NullLogger(),
        );

        self::assertFalse($reader->readPage(1, 20)->available);
    }
}
