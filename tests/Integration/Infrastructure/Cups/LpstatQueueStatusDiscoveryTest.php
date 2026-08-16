<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Integration\Infrastructure\Cups;

use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Domain\Printer\PrinterState;
use EasyPrint\Infrastructure\Cups\LpstatPrinterStatusParser;
use EasyPrint\Infrastructure\Cups\LpstatQueueStatusDiscovery;
use EasyPrint\Infrastructure\Process\ProcessFailureReason;
use EasyPrint\Infrastructure\Process\ProcessResult;
use EasyPrint\Tests\Support\FakeProcessRunner;
use PHPUnit\Framework\TestCase;

final class LpstatQueueStatusDiscoveryTest extends TestCase
{
    public function testItUsesSeparatedReadOnlyArgumentsAndNormalizesTheSnapshot(): void
    {
        $runner = new FakeProcessRunner([
            new ProcessResult('lpstat', "printer OFFICE_QUEUE is idle. enabled since date\n\tAlerts: media-needed-error\n", '', 0, 1, null),
            new ProcessResult('lpstat', 'OFFICE_QUEUE accepting requests since date', '', 0, 1, null),
        ]);
        $discovery = new LpstatQueueStatusDiscovery(
            $runner,
            new LpstatPrinterStatusParser(),
            'cups.internal',
            631,
            false,
        );

        $snapshot = $discovery->discover('OFFICE_QUEUE');

        self::assertSame(CupsConnectivity::Available, $snapshot->connectivity);
        self::assertSame(PrinterState::Ready, $snapshot->state);
        self::assertTrue($snapshot->acceptingJobs);
        self::assertSame(['media-needed-error'], $snapshot->reasons);
        self::assertSame(['-h', 'cups.internal:631', '-l', '-p', 'OFFICE_QUEUE'], $runner->calls[0]['arguments']);
        self::assertSame(['-h', 'cups.internal:631', '-a', 'OFFICE_QUEUE'], $runner->calls[1]['arguments']);
    }

    public function testItMapsAProcessFailureWithoutRunningTheSecondCommand(): void
    {
        $runner = new FakeProcessRunner([
            new ProcessResult('lpstat', '', '', 1, 1, ProcessFailureReason::TimedOut),
        ]);
        $snapshot = new LpstatQueueStatusDiscovery(
            $runner,
            new LpstatPrinterStatusParser(),
            'cups.internal',
            631,
            false,
        )->discover('OFFICE_QUEUE');

        self::assertSame(CupsConnectivity::TimedOut, $snapshot->connectivity);
        self::assertCount(1, $runner->calls);
    }
}
