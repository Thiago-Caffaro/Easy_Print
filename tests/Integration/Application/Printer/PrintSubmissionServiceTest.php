<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Integration\Application\Printer;

use function dirname;

use EasyPrint\Application\Printer\PrintDocument;
use EasyPrint\Application\Printer\PrintJobState;
use EasyPrint\Application\Printer\PrintSubmissionInput;
use EasyPrint\Application\Printer\PrintSubmissionService;
use EasyPrint\Application\Printer\ValidatedPrintArguments;
use EasyPrint\Domain\Document\StoredPdf;
use EasyPrint\Infrastructure\Cups\LpPrintJobSubmitter;
use EasyPrint\Infrastructure\Cups\LpSubmissionOutputParser;
use EasyPrint\Infrastructure\Persistence\Migrator;
use EasyPrint\Infrastructure\Persistence\SqliteConnectionFactory;
use EasyPrint\Infrastructure\Persistence\SqliteJobTitleLookup;
use EasyPrint\Infrastructure\Persistence\SqlitePrintJobRepository;
use EasyPrint\Infrastructure\Process\ProcessFailureReason;
use EasyPrint\Infrastructure\Process\ProcessResult;
use EasyPrint\Tests\Support\FakeProcessRunner;

use function file_put_contents;
use function filesize;
use function glob;
use function is_dir;
use function is_file;
use function mkdir;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

use function rmdir;

use RuntimeException;

use function str_repeat;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class PrintSubmissionServiceTest extends TestCase
{
    private string $directory;
    private string $databasePath;
    private PDO $connection;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/easy-print-submission-' . uniqid('', true);
        mkdir($this->directory);
        $this->databasePath = $this->directory . '/metadata.sqlite';
        $this->connection = SqliteConnectionFactory::create($this->databasePath);
        new Migrator($this->connection, dirname(__DIR__, 4) . '/database/migrations')->migrate();
    }

    protected function tearDown(): void
    {
        unset($this->connection);

        foreach (glob($this->directory . '/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testItPersistsAnAcceptedJobAndDeletesTheTemporaryDocument(): void
    {
        $runner = new FakeProcessRunner([
            new ProcessResult('lp', "request id is REFERENCE_QUEUE-456 (1 file(s))\n", '', 0, 4, null),
        ]);
        $document = $this->document('accepted.pdf');

        $result = $this->service($runner)->submit($this->input($document, str_repeat('a', 32)));

        self::assertFalse($result->duplicate);
        self::assertTrue($result->temporaryFileDeleted);
        self::assertFalse(is_file($document->absolutePath));
        self::assertSame(PrintJobState::Accepted, $result->record->state);
        self::assertSame(456, $result->record->cupsJobId);

        $row = $this->query('SELECT * FROM print_jobs')->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('REFERENCE_QUEUE', $row['queue_name']);
        self::assertSame('application/pdf', $row['detected_media_type']);
        self::assertSame('{"version":1,"values":{"PageSize":"A4"}}', $row['options_json']);
        self::assertSame(456, $row['cups_job_id']);
        self::assertArrayNotHasKey('document_path', $row);
        self::assertSame(3, (int) $this->query('SELECT COUNT(*) FROM job_events')->fetchColumn());
        $titles = new SqliteJobTitleLookup($this->databasePath);
        self::assertSame('original.pdf', $titles->findOriginalName('primary', 'REFERENCE_QUEUE', 456));
        self::assertNull($titles->findOriginalName('primary', 'EXTERNAL_QUEUE', 456));
    }

    public function testARepeatedSubmissionKeyReturnsTheExistingRecordWithoutCallingLpAgain(): void
    {
        $runner = new FakeProcessRunner([
            new ProcessResult('lp', "request id is REFERENCE_QUEUE-9 (1 file(s))\n", '', 0, 1, null),
        ]);
        $service = $this->service($runner);
        $key = str_repeat('b', 32);
        $first = $service->submit($this->input($this->document('first.pdf'), $key, str_repeat('1', 32)));
        $duplicateDocument = $this->document('duplicate.pdf');

        $second = $service->submit($this->input($duplicateDocument, $key, str_repeat('2', 32)));

        self::assertTrue($second->duplicate);
        self::assertTrue($second->temporaryFileDeleted);
        self::assertFalse(is_file($duplicateDocument->absolutePath));
        self::assertSame($first->record->id, $second->record->id);
        self::assertCount(1, $runner->calls);
        self::assertSame(1, (int) $this->query('SELECT COUNT(*) FROM print_jobs')->fetchColumn());
        self::assertSame(1, (int) $this->query('SELECT COUNT(*) FROM print_submission_keys')->fetchColumn());
    }

    public function testARejectedSubmissionKeepsOnlySafeDiagnosticsAndDeletesTheDocument(): void
    {
        $runner = new FakeProcessRunner([
            new ProcessResult('lp', '', 'private server and document details', 1, 17, ProcessFailureReason::NonZeroExit),
        ]);
        $document = $this->document('rejected.pdf');

        $result = $this->service($runner)->submit($this->input($document, str_repeat('c', 32)));

        self::assertSame(PrintJobState::Failed, $result->record->state);
        self::assertSame('cups_rejected_submission', $result->record->safeErrorCode);
        self::assertStringNotContainsString('private', $result->record->safeErrorDetail ?? '');
        self::assertFalse(is_file($document->absolutePath));
        self::assertNotNull($this->query('SELECT finished_at FROM print_jobs')->fetchColumn());
    }

    public function testATimeoutIsDurablyIndeterminateAndTheDocumentIsDeleted(): void
    {
        $runner = new FakeProcessRunner([
            new ProcessResult('lp', '', '', null, 15_000, ProcessFailureReason::TimedOut),
        ]);
        $document = $this->document('timeout.pdf');

        $result = $this->service($runner)->submit($this->input($document, str_repeat('d', 32)));

        self::assertSame(PrintJobState::Indeterminate, $result->record->state);
        self::assertSame('cups_submission_timeout', $result->record->safeErrorCode);
        self::assertNull($result->record->cupsJobId);
        self::assertFalse(is_file($document->absolutePath));
        self::assertNull($this->query('SELECT finished_at FROM print_jobs')->fetchColumn());
    }

    public function testCupsReconciliationRecordsUnknownAndCancellationWithoutOverwritingFinalState(): void
    {
        $runner = new FakeProcessRunner([
            new ProcessResult('lp', "request id is REFERENCE_QUEUE-77 (1 file(s))\n", '', 0, 1, null),
        ]);
        $this->service($runner)->submit($this->input($this->document('reconcile.pdf'), str_repeat('e', 32)));
        $repository = new SqlitePrintJobRepository($this->connection);

        self::assertTrue($repository->reconcile(
            'primary',
            'REFERENCE_QUEUE',
            77,
            PrintJobState::Indeterminate,
            'cups_state_unknown',
            '2026-08-16T12:00:00Z',
        ));
        self::assertTrue($repository->reconcile(
            'primary',
            'REFERENCE_QUEUE',
            77,
            PrintJobState::Cancelled,
            null,
            '2026-08-16T12:01:00Z',
        ));
        self::assertFalse($repository->reconcile(
            'primary',
            'REFERENCE_QUEUE',
            77,
            PrintJobState::Processing,
            null,
            '2026-08-16T12:02:00Z',
        ));

        $row = $this->query('SELECT state, finished_at, safe_error_code FROM print_jobs')->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('cancelled', $row['state']);
        self::assertSame('2026-08-16T12:01:00Z', $row['finished_at']);
        self::assertSame('cups_state_unknown', $row['safe_error_code']);
        self::assertSame(2, (int) $this->query("SELECT COUNT(*) FROM job_events WHERE source = 'cups'")->fetchColumn());
    }

    public function testRetentionCleanupDeletesOnlyExpiredMetadataAndItsDependentRecords(): void
    {
        $runner = new FakeProcessRunner([
            new ProcessResult('lp', "request id is REFERENCE_QUEUE-78 (1 file(s))\n", '', 0, 1, null),
        ]);
        $this->service($runner)->submit($this->input($this->document('expired.pdf'), str_repeat('f', 32)));
        $this->connection->exec("UPDATE print_jobs SET retained_until = '2026-01-01T00:00:00Z'");

        $deleted = new SqlitePrintJobRepository($this->connection)->deleteExpired('2026-08-16T00:00:00Z');

        self::assertSame(1, $deleted);
        self::assertSame(0, (int) $this->query('SELECT COUNT(*) FROM print_jobs')->fetchColumn());
        self::assertSame(0, (int) $this->query('SELECT COUNT(*) FROM print_submission_keys')->fetchColumn());
        self::assertSame(0, (int) $this->query('SELECT COUNT(*) FROM job_events')->fetchColumn());
    }

    private function service(FakeProcessRunner $runner): PrintSubmissionService
    {
        return new PrintSubmissionService(
            new SqlitePrintJobRepository($this->connection),
            new LpPrintJobSubmitter($runner, new LpSubmissionOutputParser(), 'cups.internal', 631, false),
            90,
        );
    }

    private function input(
        PrintDocument $document,
        string $submissionKey,
        string $correlationId = '0123456789abcdef0123456789abcdef',
    ): PrintSubmissionInput {
        return new PrintSubmissionInput(
            submissionKey: $submissionKey,
            correlationId: $correlationId,
            cupsServerKey: 'primary',
            document: $document,
            arguments: new ValidatedPrintArguments(
                queueIdentifier: 'REFERENCE_QUEUE',
                copies: 2,
                pageRange: '1-2',
                selectedOptions: ['PageSize' => 'A4'],
                arguments: ['-d', 'REFERENCE_QUEUE', '-n', '2', '-P', '1-2', '-o', 'PageSize=A4'],
            ),
        );
    }

    private function document(string $name): PrintDocument
    {
        $path = $this->directory . '/' . $name;
        file_put_contents($path, '%PDF synthetic test document');

        return PrintDocument::fromStoredPdf(new StoredPdf(
            storedName: $name,
            absolutePath: $path,
            originalName: 'original.pdf',
            byteSize: (int) filesize($path),
            mediaType: 'application/pdf',
        ));
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
