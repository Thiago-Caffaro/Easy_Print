<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Integration\Http;

use function dirname;

use EasyPrint\Application\Printer\PrintHistoryEntry;
use EasyPrint\Application\Printer\PrintHistoryPage;
use EasyPrint\Application\Printer\PrintJobState;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Domain\Printer\QueueSnapshot;
use EasyPrint\Tests\Support\FakePrintHistoryReader;
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

final class PrintHistoryActionTest extends TestCase
{
    private string $runtimeDirectory;

    protected function setUp(): void
    {
        $this->runtimeDirectory = sys_get_temp_dir() . '/easy-print-history-http-' . uniqid('', true);
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

    public function testItRendersEscapedMetadataWithoutDocumentActions(): void
    {
        $entry = new PrintHistoryEntry(
            id: 'local-id',
            queueName: '<queue>',
            cupsJobId: 42,
            originalName: '<private>.pdf',
            mediaType: 'application/pdf',
            byteSize: 2_048,
            copies: 2,
            pageRange: '1-3',
            selectedOptions: ['PageSize' => 'A4'],
            state: PrintJobState::Completed,
            safeErrorCode: null,
            submittedAt: '2026-08-13T10:30:00Z',
            updatedAt: '2026-08-13T10:31:00Z',
            finishedAt: '2026-08-13T10:31:00Z',
        );
        $application = $this->application(new PrintHistoryPage([$entry], 1, 20, 1));
        $request = new ServerRequestFactory()->createServerRequest('GET', '/history?lang=en')
            ->withQueryParams(['lang' => 'en']);
        $body = (string) $application->handle($request)->getBody();

        self::assertStringContainsString('Print history', $body);
        self::assertStringContainsString('&lt;private&gt;.pdf', $body);
        self::assertStringContainsString('&lt;queue&gt;', $body);
        self::assertStringContainsString('PageSize: A4', $body);
        self::assertStringContainsString('Completed', $body);
        self::assertDoesNotMatchRegularExpression('/<a[^>]+download/i', $body);
        self::assertStringNotContainsString('>Reprint<', $body);

        $localizedRequest = new ServerRequestFactory()->createServerRequest('GET', '/history?lang=pt-BR')
            ->withQueryParams(['lang' => 'pt-BR']);
        $localizedBody = (string) $application->handle($localizedRequest)->getBody();
        self::assertStringContainsString('2,0 KB', $localizedBody);
    }

    public function testItRendersUnavailableAndEmptyStates(): void
    {
        $request = new ServerRequestFactory()->createServerRequest('GET', '/history');
        $unavailable = (string) $this->application(PrintHistoryPage::unavailable(1, 20))
            ->handle($request)->getBody();
        $empty = (string) $this->application(new PrintHistoryPage([], 1, 20, 0))
            ->handle($request)->getBody();

        self::assertStringContainsString('temporariamente indisponível', $unavailable);
        self::assertStringContainsString('Nenhum histórico', $empty);
    }

    /** @return App<null> */
    private function application(PrintHistoryPage $page): App
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
            queueDiscovery: new FakeQueueDiscovery(new QueueSnapshot(CupsConnectivity::Available, [])),
            printHistoryReader: new FakePrintHistoryReader($page),
        );
    }
}
