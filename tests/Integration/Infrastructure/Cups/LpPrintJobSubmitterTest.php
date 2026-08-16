<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Integration\Infrastructure\Cups;

use function dirname;

use EasyPrint\Application\Printer\PrintDocument;
use EasyPrint\Application\Printer\PrintJobState;
use EasyPrint\Application\Printer\ValidatedPrintArguments;
use EasyPrint\Domain\Document\StoredPdf;
use EasyPrint\Infrastructure\Cups\LpPrintJobSubmitter;
use EasyPrint\Infrastructure\Cups\LpSubmissionOutputParser;
use EasyPrint\Infrastructure\Process\ProcessFailureReason;
use EasyPrint\Infrastructure\Process\ProcessResult;
use EasyPrint\Tests\Support\FakeProcessRunner;

use function file_get_contents;
use function json_decode;

use const JSON_THROW_ON_ERROR;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LpPrintJobSubmitterTest extends TestCase
{
    public function testItSubmitsASeparatedArgumentListAndParsesTheFixtureJobId(): void
    {
        $fixture = $this->fixture();
        $runner = new FakeProcessRunner([
            new ProcessResult('lp', $fixture['stdout'], '', 0, 8, null),
        ]);
        $submitter = new LpPrintJobSubmitter(
            $runner,
            new LpSubmissionOutputParser(),
            'cups.internal',
            631,
            false,
        );

        $result = $submitter->submit($this->arguments(), $this->document('/private/uploads/random.pdf'));

        self::assertSame(PrintJobState::Accepted, $result->state);
        self::assertSame($fixture['expectedJobId'], $result->cupsJobId);
        self::assertSame([
            'executableKey' => 'lp',
            'arguments' => [
                '-h', 'cups.internal:631',
                '-d', 'REFERENCE_QUEUE', '-n', '2', '-P', '1,3-5', '-o', 'PageSize=A4',
                '--', '/private/uploads/random.pdf',
            ],
            'environmentOverrides' => [],
        ], $runner->calls[0]);
    }

    public function testItRequestsEncryptionAndFormatsIpv6(): void
    {
        $runner = new FakeProcessRunner([
            new ProcessResult('lp', "request id is REFERENCE_QUEUE-1 (1 file(s))\n", '', 0, 1, null),
        ]);
        $submitter = new LpPrintJobSubmitter(
            $runner,
            new LpSubmissionOutputParser(),
            '2001:db8::10',
            8631,
            true,
        );

        $submitter->submit($this->arguments(), $this->document('/private/random.pdf'));

        self::assertSame(['-E', '-h', '[2001:db8::10]:8631'], array_slice($runner->calls[0]['arguments'], 0, 3));
    }

    #[DataProvider('failureProvider')]
    public function testItMapsProcessFailuresWithoutRetainingProcessOutput(
        ProcessFailureReason $reason,
        PrintJobState $state,
        string $safeCode,
    ): void {
        $runner = new FakeProcessRunner([
            new ProcessResult('lp', 'private stdout', 'private stderr', 1, 99, $reason),
        ]);
        $submitter = new LpPrintJobSubmitter(
            $runner,
            new LpSubmissionOutputParser(),
            'cups.internal',
            631,
            false,
        );

        $result = $submitter->submit($this->arguments(), $this->document('/private/random.pdf'));

        self::assertSame($state, $result->state);
        self::assertSame($safeCode, $result->safeErrorCode);
        self::assertStringNotContainsString('private', $result->safeDiagnostic ?? '');
    }

    /**
     * @return iterable<string,array{ProcessFailureReason,PrintJobState,string}>
     */
    public static function failureProvider(): iterable
    {
        yield 'timeout is ambiguous' => [ProcessFailureReason::TimedOut, PrintJobState::Indeterminate, 'cups_submission_timeout'];
        yield 'bounded output is ambiguous' => [ProcessFailureReason::OutputLimit, PrintJobState::Indeterminate, 'cups_response_too_large'];
        yield 'nonzero exit is rejected' => [ProcessFailureReason::NonZeroExit, PrintJobState::Failed, 'cups_rejected_submission'];
        yield 'start failure' => [ProcessFailureReason::StartFailed, PrintJobState::Failed, 'cups_process_unavailable'];
        yield 'invalid runner argument' => [ProcessFailureReason::InvalidArgument, PrintJobState::Failed, 'submission_configuration_error'];
        yield 'executable not allowed' => [ProcessFailureReason::NotAllowed, PrintJobState::Failed, 'submission_configuration_error'];
    }

    public function testSuccessfulMalformedOutputIsIndeterminate(): void
    {
        $runner = new FakeProcessRunner([
            new ProcessResult('lp', 'unexpected private response', '', 0, 2, null),
        ]);
        $submitter = new LpPrintJobSubmitter(
            $runner,
            new LpSubmissionOutputParser(),
            'cups.internal',
            631,
            false,
        );

        $result = $submitter->submit($this->arguments(), $this->document('/private/random.pdf'));

        self::assertSame(PrintJobState::Indeterminate, $result->state);
        self::assertSame('cups_response_unrecognized', $result->safeErrorCode);
        self::assertStringNotContainsString('unexpected', $result->safeDiagnostic ?? '');
    }

    /**
     * @return array{stdout:string,expectedJobId:int}
     */
    private function fixture(): array
    {
        $contents = file_get_contents(dirname(__DIR__, 3) . '/Fixtures/Cups/Contract/submission/accepted-job.json');
        self::assertIsString($contents);
        $fixture = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($fixture);
        self::assertSame('synthetic-contract', $fixture['kind'] ?? null);

        return [
            'stdout' => (string) $fixture['stdout'],
            'expectedJobId' => (int) $fixture['expectedJobId'],
        ];
    }

    private function arguments(): ValidatedPrintArguments
    {
        return new ValidatedPrintArguments(
            queueIdentifier: 'REFERENCE_QUEUE',
            copies: 2,
            pageRange: '1,3-5',
            selectedOptions: ['PageSize' => 'A4'],
            arguments: ['-d', 'REFERENCE_QUEUE', '-n', '2', '-P', '1,3-5', '-o', 'PageSize=A4'],
        );
    }

    private function document(string $path): PrintDocument
    {
        return PrintDocument::fromStoredPdf(new StoredPdf(
            storedName: 'random.pdf',
            absolutePath: $path,
            originalName: 'document.pdf',
            byteSize: 123,
            mediaType: 'application/pdf',
        ));
    }
}
