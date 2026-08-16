<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Integration\Infrastructure\Cups;

use function dirname;

use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Infrastructure\Cups\LpstatActiveJobDiscovery;
use EasyPrint\Infrastructure\Cups\LpstatJobOutputParser;
use EasyPrint\Infrastructure\Cups\LpstatOutputParser;
use EasyPrint\Infrastructure\Process\ProcessFailureReason;
use EasyPrint\Infrastructure\Process\ProcessResult;
use EasyPrint\Tests\Support\FakeProcessRunner;

use function file_get_contents;
use function json_decode;

use const JSON_THROW_ON_ERROR;

use PHPUnit\Framework\TestCase;

final class LpstatActiveJobDiscoveryTest extends TestCase
{
    public function testItDiscoversAndNormalizesActiveJobsFromTheContractFixture(): void
    {
        $fixture = $this->fixture();
        $runner = new FakeProcessRunner([
            $this->success($fixture['jobsStdout']),
            $this->success($fixture['printersStdout']),
        ]);

        $snapshot = $this->discovery($runner)->discover();

        self::assertSame(CupsConnectivity::Available, $snapshot->connectivity);
        self::assertSame($fixture['expected'], array_map(static fn($job): array => [
            'queueIdentifier' => $job->queueIdentifier,
            'cupsJobId' => $job->cupsJobId,
            'byteSize' => $job->byteSize,
            'state' => $job->state->value,
        ], $snapshot->jobs));
        self::assertSame([
            ['executableKey' => 'lpstat', 'arguments' => ['-h', 'cups.internal:631', '-W', 'not-completed', '-o'], 'environmentOverrides' => []],
            ['executableKey' => 'lpstat', 'arguments' => ['-h', 'cups.internal:631', '-p'], 'environmentOverrides' => []],
        ], $runner->calls);
    }

    public function testAnEmptySuccessfulListDoesNotRunThePrinterStateQuery(): void
    {
        $runner = new FakeProcessRunner([$this->success('')]);

        $snapshot = $this->discovery($runner)->discover();

        self::assertSame(CupsConnectivity::Available, $snapshot->connectivity);
        self::assertSame([], $snapshot->jobs);
        self::assertCount(1, $runner->calls);
    }

    public function testAStateQueryFailureKeepsJobsWithUnknownState(): void
    {
        $runner = new FakeProcessRunner([
            $this->success("REFERENCE_QUEUE-1 user 1024   Wed 13 Aug 2026 10:30:00 AM UTC\n"),
            $this->failure(ProcessFailureReason::TimedOut),
        ]);

        $snapshot = $this->discovery($runner)->discover();

        self::assertSame(CupsConnectivity::Available, $snapshot->connectivity);
        self::assertSame('unknown', $snapshot->jobs[0]->state->value);
    }

    public function testAJobQueryTimeoutIsDistinctFromAnEmptyList(): void
    {
        $runner = new FakeProcessRunner([$this->failure(ProcessFailureReason::TimedOut)]);

        $snapshot = $this->discovery($runner)->discover();

        self::assertSame(CupsConnectivity::TimedOut, $snapshot->connectivity);
        self::assertSame([], $snapshot->jobs);
    }

    public function testMalformedJobOutputIsAStableConnectivityResult(): void
    {
        $runner = new FakeProcessRunner([$this->success('private unexpected output')]);

        $snapshot = $this->discovery($runner)->discover();

        self::assertSame(CupsConnectivity::MalformedResponse, $snapshot->connectivity);
        self::assertSame([], $snapshot->jobs);
    }

    public function testItRequestsEncryptionAndFormatsIpv6(): void
    {
        $runner = new FakeProcessRunner([$this->success('')]);
        $discovery = new LpstatActiveJobDiscovery(
            $runner,
            new LpstatJobOutputParser(),
            new LpstatOutputParser(),
            '2001:db8::10',
            8631,
            true,
        );

        $discovery->discover();

        self::assertSame(['-E', '-h', '[2001:db8::10]:8631', '-W', 'not-completed', '-o'], $runner->calls[0]['arguments']);
    }

    /**
     * @return array{
     *   jobsStdout:string,
     *   printersStdout:string,
     *   expected:list<array{queueIdentifier:string,cupsJobId:int,byteSize:int,state:string}>
     * }
     */
    private function fixture(): array
    {
        $contents = file_get_contents(dirname(__DIR__, 3) . '/Fixtures/Cups/Contract/active-jobs/multiple-jobs.json');
        self::assertIsString($contents);
        $fixture = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($fixture);
        self::assertSame('synthetic-contract', $fixture['kind'] ?? null);

        /** @var array{
         *   jobsStdout:string,
         *   printersStdout:string,
         *   expected:list<array{queueIdentifier:string,cupsJobId:int,byteSize:int,state:string}>
         * } $fixture
         */
        return $fixture;
    }

    private function discovery(FakeProcessRunner $runner): LpstatActiveJobDiscovery
    {
        return new LpstatActiveJobDiscovery(
            $runner,
            new LpstatJobOutputParser(),
            new LpstatOutputParser(),
            'cups.internal',
            631,
            false,
        );
    }

    private function success(string $stdout): ProcessResult
    {
        return new ProcessResult('lpstat', $stdout, '', 0, 1, null);
    }

    private function failure(ProcessFailureReason $reason): ProcessResult
    {
        return new ProcessResult('lpstat', '', '', 1, 1, $reason);
    }
}
