<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Unit\Application\Printer;

use EasyPrint\Application\Printer\ActiveJobDiscovery;
use EasyPrint\Application\Printer\CancelJobStatus;
use EasyPrint\Application\Printer\PrintJobCancellation;
use EasyPrint\Domain\Printer\ActiveJobSnapshot;
use EasyPrint\Domain\Printer\ActiveJobState;
use EasyPrint\Domain\Printer\ActivePrintJob;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Infrastructure\Configuration\ConfigurationLoader;
use EasyPrint\Infrastructure\Process\ProcessFailureReason;
use EasyPrint\Infrastructure\Process\ProcessResult;
use EasyPrint\Tests\Support\FakeProcessRunner;
use PHPUnit\Framework\TestCase;

final class PrintJobCancellationTest extends TestCase
{
    public function testItCancelsAnEligibleJobAndReconcilesItsRemoval(): void
    {
        $runner = new FakeProcessRunner([$this->success()]);
        $service = $this->service($runner, [
            $this->snapshot(ActiveJobState::Processing),
            new ActiveJobSnapshot(CupsConnectivity::Available),
        ]);

        $result = $service->cancel('REFERENCE_QUEUE', 42);

        self::assertSame(CancelJobStatus::Cancelled, $result->status);
        self::assertSame(['-h', 'cups.internal:631', 'REFERENCE_QUEUE-42'], $runner->calls[0]['arguments']);
    }

    public function testItRejectsUnknownAndNonCancelableJobsWithoutCallingCancel(): void
    {
        $runner = new FakeProcessRunner([]);
        $service = $this->service($runner, [
            $this->snapshot(ActiveJobState::Unknown),
        ]);

        self::assertSame(CancelJobStatus::NotCancelable, $service->cancel('REFERENCE_QUEUE', 42)->status);
        self::assertSame([], $runner->calls);
    }

    public function testItRejectsStaleJobsAndDoesNotGuessCancellation(): void
    {
        $runner = new FakeProcessRunner([]);
        $service = $this->service($runner, [new ActiveJobSnapshot(CupsConnectivity::Available)]);

        self::assertSame(CancelJobStatus::NotFound, $service->cancel('REFERENCE_QUEUE', 42)->status);
        self::assertSame([], $runner->calls);
    }

    public function testItReportsUnavailableWhenReconciliationCannotConfirmTheResult(): void
    {
        $runner = new FakeProcessRunner([$this->success()]);
        $service = $this->service($runner, [
            $this->snapshot(ActiveJobState::Pending),
            ActiveJobSnapshot::failed(CupsConnectivity::TimedOut),
        ]);

        self::assertSame(CancelJobStatus::Unavailable, $service->cancel('REFERENCE_QUEUE', 42)->status);
    }

    public function testItUsesEncryptionAndReportsAProcessFailureSafely(): void
    {
        $runner = new FakeProcessRunner([
            new ProcessResult('cancel', 'private output', 'private error', 1, 5, ProcessFailureReason::NonZeroExit),
        ]);
        $service = $this->service($runner, [$this->snapshot(ActiveJobState::Pending)], true);

        $result = $service->cancel('REFERENCE_QUEUE', 42);

        self::assertSame(CancelJobStatus::Failed, $result->status);
        self::assertSame('non_zero_exit', $result->diagnostic);
        self::assertSame(['-E', '-h', 'cups.internal:631', 'REFERENCE_QUEUE-42'], $runner->calls[0]['arguments']);
    }

    /** @param list<ActiveJobSnapshot> $snapshots */
    private function service(FakeProcessRunner $runner, array $snapshots, bool $encrypted = false): PrintJobCancellation
    {
        $discovery = new class ($snapshots) implements ActiveJobDiscovery {
            /** @param list<ActiveJobSnapshot> $snapshots */
            public function __construct(private array $snapshots) {}

            public function discover(): ActiveJobSnapshot
            {
                return array_shift($this->snapshots) ?? ActiveJobSnapshot::failed(CupsConnectivity::Unavailable);
            }
        };
        $environment = [
            'CUPS_HOST' => 'cups.internal',
            'CUPS_PORT' => '631',
            'CUPS_ENCRYPTION' => $encrypted ? 'required' : 'never',
        ];

        return new PrintJobCancellation(
            ConfigurationLoader::load($environment, dirname(__DIR__, 4)),
            $discovery,
            $runner,
        );
    }

    private function snapshot(ActiveJobState $state): ActiveJobSnapshot
    {
        return new ActiveJobSnapshot(CupsConnectivity::Available, [
            new ActivePrintJob('REFERENCE_QUEUE', 42, 10, 'now', $state),
        ]);
    }

    private function success(): ProcessResult
    {
        return new ProcessResult('cancel', '', '', 0, 4, null);
    }
}
