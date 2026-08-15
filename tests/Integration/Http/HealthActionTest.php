<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Integration\Http;

use function dirname;

use EasyPrint\Application\Health\ReadinessReport;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Domain\Printer\QueueSnapshot;
use EasyPrint\Tests\Support\FakeQueueDiscovery;
use EasyPrint\Tests\Support\FakeReadinessProbe;

use function json_decode;

use const JSON_THROW_ON_ERROR;

use function mkdir;

use PHPUnit\Framework\TestCase;

use function rmdir;

use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class HealthActionTest extends TestCase
{
    private string $runtimeDirectory;

    protected function setUp(): void
    {
        $this->runtimeDirectory = sys_get_temp_dir() . '/easy-print-health-' . uniqid('', true);
        mkdir($this->runtimeDirectory);
    }

    protected function tearDown(): void
    {
        if (is_file($this->runtimeDirectory . '/temporary/csrf-secret')) {
            unlink($this->runtimeDirectory . '/temporary/csrf-secret');
        }

        if (is_dir($this->runtimeDirectory . '/temporary')) {
            rmdir($this->runtimeDirectory . '/temporary');
        }

        if (is_dir($this->runtimeDirectory . '/database')) {
            rmdir($this->runtimeDirectory . '/database');
        }

        rmdir($this->runtimeDirectory);
    }

    public function testLivenessIsDependencyFreeAndCarriesTheRequestIdentifier(): void
    {
        $probe = new FakeReadinessProbe(new ReadinessReport(false, false, CupsConnectivity::Unavailable));
        $response = $this->application($probe)->handle(
            new ServerRequestFactory()->createServerRequest('GET', '/health/live'),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, $probe->calls);
        self::assertSame('application/json; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $response->getHeaderLine('X-Request-ID'));
        self::assertSame([
            'status' => 'ok',
            'checks' => ['application' => 'ok'],
        ], json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function testReadinessReportsHealthyAndDegradedDependenciesWithoutQueueData(): void
    {
        $ready = $this->application(new FakeReadinessProbe(
            new ReadinessReport(true, true, CupsConnectivity::Available),
        ))->handle(new ServerRequestFactory()->createServerRequest('GET', '/health/ready'));
        $degraded = $this->application(new FakeReadinessProbe(
            new ReadinessReport(true, true, CupsConnectivity::TimedOut),
        ))->handle(new ServerRequestFactory()->createServerRequest('GET', '/health/ready'));

        self::assertSame(200, $ready->getStatusCode());
        self::assertSame('ok', json_decode((string) $ready->getBody(), true, flags: JSON_THROW_ON_ERROR)['status']);
        self::assertSame(200, $degraded->getStatusCode());
        $payload = json_decode((string) $degraded->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('degraded', $payload['status']);
        self::assertSame('timed_out', $payload['checks']['cups']);
        self::assertArrayNotHasKey('queues', $payload);
    }

    public function testReadinessReturnsServiceUnavailableForCriticalLocalState(): void
    {
        $response = $this->application(new FakeReadinessProbe(
            new ReadinessReport(true, false, CupsConnectivity::Available),
        ))->handle(new ServerRequestFactory()->createServerRequest('GET', '/health/ready'));

        self::assertSame(503, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('unavailable', $payload['status']);
        self::assertSame('unavailable', $payload['checks']['database']);
    }

    public function testHealthRoutesFollowTheConfiguredBasePath(): void
    {
        $probe = new FakeReadinessProbe(new ReadinessReport(true, true, CupsConnectivity::Available));
        $response = $this->application($probe, '/print')->handle(
            new ServerRequestFactory()->createServerRequest('GET', '/print/health/live'),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, $probe->calls);
    }

    /**
     * @return App<null>
     */
    private function application(FakeReadinessProbe $probe, string $basePath = ''): App
    {
        $root = dirname(__DIR__, 3);
        $createApplication = require $root . '/config/bootstrap.php';

        return $createApplication([
            'APP_ENV' => 'testing',
            'APP_BASE_PATH' => $basePath,
            'DATABASE_PATH' => $this->runtimeDirectory . '/database/easy-print.sqlite',
            'TEMPORARY_PATH' => $this->runtimeDirectory . '/temporary',
        ], $root, new FakeQueueDiscovery(new QueueSnapshot(CupsConnectivity::Available)), $probe);
    }
}
