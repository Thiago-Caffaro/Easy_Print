<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Unit\Application\Health;

use EasyPrint\Application\Health\HealthStatus;
use EasyPrint\Application\Health\ReadinessReport;
use EasyPrint\Domain\Printer\CupsConnectivity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReadinessReportTest extends TestCase
{
    #[DataProvider('statusProvider')]
    public function testItSeparatesCriticalReadinessFromCupsDegradation(
        bool $storageReady,
        bool $databaseReady,
        CupsConnectivity $cupsConnectivity,
        HealthStatus $expected,
    ): void {
        $report = new ReadinessReport($storageReady, $databaseReady, $cupsConnectivity);

        self::assertSame($expected, $report->status());
        self::assertSame($cupsConnectivity->value, $report->checks()['cups']);
    }

    /**
     * @return iterable<string,array{bool,bool,CupsConnectivity,HealthStatus}>
     */
    public static function statusProvider(): iterable
    {
        yield 'ready' => [true, true, CupsConnectivity::Available, HealthStatus::Ok];
        yield 'CUPS outage is degraded' => [true, true, CupsConnectivity::TimedOut, HealthStatus::Degraded];
        yield 'storage is critical' => [false, true, CupsConnectivity::Available, HealthStatus::Unavailable];
        yield 'database is critical' => [true, false, CupsConnectivity::Available, HealthStatus::Unavailable];
    }
}
