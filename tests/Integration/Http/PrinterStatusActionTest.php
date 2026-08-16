<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Integration\Http;

use function dirname;

use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Domain\Printer\PrinterQueue;
use EasyPrint\Domain\Printer\PrinterState;
use EasyPrint\Domain\Printer\PrinterStatusSnapshot;
use EasyPrint\Domain\Printer\QueueSnapshot;
use EasyPrint\Tests\Support\FakeQueueDiscovery;
use EasyPrint\Tests\Support\FakeQueueStatusDiscovery;

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

final class PrinterStatusActionTest extends TestCase
{
    private string $runtimeDirectory;

    protected function setUp(): void
    {
        $this->runtimeDirectory = sys_get_temp_dir() . '/easy-print-status-http-' . uniqid('', true);
        mkdir($this->runtimeDirectory);
    }

    protected function tearDown(): void
    {
        if (is_file($this->runtimeDirectory . '/temporary/csrf-secret')) {
            unlink($this->runtimeDirectory . '/temporary/csrf-secret');
        }

        foreach (['temporary', 'database'] as $directory) {
            if (is_dir($this->runtimeDirectory . '/' . $directory)) {
                rmdir($this->runtimeDirectory . '/' . $directory);
            }
        }

        rmdir($this->runtimeDirectory);
    }

    public function testItMapsKnownReasonsAndShowsSanitizedUnknownReasons(): void
    {
        $application = $this->application(new PrinterStatusSnapshot(
            CupsConnectivity::Available,
            'REFERENCE_QUEUE',
            PrinterState::Processing,
            true,
            ['media-needed-error', 'vendor.condition-warning'],
        ));
        $request = new ServerRequestFactory()->createServerRequest(
            'GET',
            '/printer/status?queue=REFERENCE_QUEUE&lang=pt-BR',
        )->withQueryParams(['queue' => 'REFERENCE_QUEUE', 'lang' => 'pt-BR']);
        $body = (string) $application->handle($request)->getBody();

        self::assertStringContainsString('Coloque papel na impressora.', $body);
        self::assertStringContainsString('vendor.condition-warning', $body);
        self::assertStringContainsString('hx-trigger="every 5s"', $body);
        self::assertStringNotContainsString('nível de tinta', $body);
    }

    public function testAnUnknownQueueIsNotPresentedAsAvailable(): void
    {
        $application = $this->application(new PrinterStatusSnapshot(
            CupsConnectivity::Available,
            'REFERENCE_QUEUE',
            PrinterState::Ready,
            true,
            [],
        ));
        $request = new ServerRequestFactory()->createServerRequest('GET', '/printer/status?queue=UNKNOWN')
            ->withQueryParams(['queue' => 'UNKNOWN']);
        $body = (string) $application->handle($request)->getBody();

        self::assertStringContainsString('temporariamente indisponível', $body);
        self::assertStringNotContainsString('CUPS não informa nenhuma condição', $body);
    }

    /** @return App<null> */
    private function application(PrinterStatusSnapshot $status): App
    {
        $root = dirname(__DIR__, 3);
        $createApplication = require $root . '/config/bootstrap.php';

        return $createApplication(
            environment: [
                'APP_ENV' => 'testing',
                'DATABASE_PATH' => $this->runtimeDirectory . '/database/easy-print.sqlite',
                'TEMPORARY_PATH' => $this->runtimeDirectory . '/temporary',
            ],
            projectRoot: $root,
            queueDiscovery: new FakeQueueDiscovery(new QueueSnapshot(
                CupsConnectivity::Available,
                [new PrinterQueue('REFERENCE_QUEUE', PrinterState::Processing)],
            )),
            queueStatusDiscovery: new FakeQueueStatusDiscovery($status),
        );
    }
}
