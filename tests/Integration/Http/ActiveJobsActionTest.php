<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Integration\Http;

use function dirname;

use EasyPrint\Domain\Printer\ActiveJobSnapshot;
use EasyPrint\Domain\Printer\ActiveJobState;
use EasyPrint\Domain\Printer\ActivePrintJob;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Domain\Printer\QueueSnapshot;
use EasyPrint\Tests\Support\FakeActiveJobDiscovery;
use EasyPrint\Tests\Support\FakeJobTitleLookup;
use EasyPrint\Tests\Support\FakeQueueDiscovery;

use function is_dir;
use function is_file;
use function mkdir;

use PHPUnit\Framework\TestCase;

use function rmdir;

use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class ActiveJobsActionTest extends TestCase
{
    private string $runtimeDirectory;

    protected function setUp(): void
    {
        $this->runtimeDirectory = sys_get_temp_dir() . '/easy-print-active-jobs-http-' . uniqid('', true);
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

        if (is_dir($this->runtimeDirectory)) {
            rmdir($this->runtimeDirectory);
        }
    }

    public function testItRendersEscapedLocalAndExternalJobsWithUsefulPolling(): void
    {
        $snapshot = new ActiveJobSnapshot(CupsConnectivity::Available, [
            new ActivePrintJob('REFERENCE_QUEUE', 42, 2_048, 'Wed 13 Aug 2026 10:30:00 AM UTC', ActiveJobState::Processing),
            new ActivePrintJob('OFFICE_COLOR', 7, 1_024, 'Wed 13 Aug 2026 10:31:00 AM UTC', ActiveJobState::Pending),
        ]);
        $application = $this->application(
            $snapshot,
            new FakeJobTitleLookup(['REFERENCE_QUEUE-42' => '<private document>.pdf']),
        );
        $request = new ServerRequestFactory()
            ->createServerRequest('GET', '/jobs/active?lang=en')
            ->withQueryParams(['lang' => 'en']);

        $response = $application->handle($request);
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('hx-trigger="every 3s"', $body);
        self::assertStringContainsString('&lt;private document&gt;.pdf', $body);
        self::assertStringNotContainsString('<private document>', $body);
        self::assertStringContainsString('Job created outside Easy Print', $body);
        self::assertStringContainsString('REFERENCE_QUEUE', $body);
        self::assertStringContainsString('Printing', $body);
        self::assertStringContainsString('Pending', $body);
        self::assertStringContainsString('2.0 KB', $body);
        self::assertStringContainsString('name="_csrf"', $body);
        self::assertStringContainsString('Cancel job', $body);
    }

    public function testUnavailableJobsRenderAStableErrorAndSlowerPolling(): void
    {
        $application = $this->application(ActiveJobSnapshot::failed(CupsConnectivity::TimedOut));
        $request = new ServerRequestFactory()
            ->createServerRequest('GET', '/jobs/active?lang=en')
            ->withQueryParams(['lang' => 'en']);

        $body = (string) $application->handle($request)->getBody();

        self::assertStringContainsString('Timed out', $body);
        self::assertStringContainsString('hx-trigger="every 15s"', $body);
        self::assertStringContainsString('Print jobs could not be loaded', $body);
    }

    public function testAnEmptyAvailableListStopsAutomaticPolling(): void
    {
        $application = $this->application(new ActiveJobSnapshot(CupsConnectivity::Available));
        $request = new ServerRequestFactory()
            ->createServerRequest('GET', '/jobs/active?lang=en')
            ->withQueryParams(['lang' => 'en']);

        $body = (string) $application->handle($request)->getBody();

        self::assertStringContainsString('No print jobs are active.', $body);
        self::assertStringNotContainsString('hx-trigger="every 3s"', $body);
        self::assertStringNotContainsString('hx-trigger="every 15s"', $body);
    }

    /** @return App<null> */
    private function application(
        ActiveJobSnapshot $snapshot,
        ?FakeJobTitleLookup $titleLookup = null,
    ): App {
        $root = dirname(__DIR__, 3);
        $createApplication = require $root . '/config/bootstrap.php';

        return $createApplication(
            [
                'APP_ENV' => 'testing',
                'DATABASE_PATH' => $this->runtimeDirectory . '/database/easy-print.sqlite',
                'TEMPORARY_PATH' => $this->runtimeDirectory . '/temporary',
            ],
            $root,
            new FakeQueueDiscovery(new QueueSnapshot(CupsConnectivity::Available)),
            null,
            new FakeActiveJobDiscovery($snapshot),
            $titleLookup ?? new FakeJobTitleLookup(),
        );
    }
}
