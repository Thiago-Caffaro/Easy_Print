<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Integration\Http;

use function dirname;

use EasyPrint\Domain\Printer\CapabilitySnapshot;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Domain\Printer\PrinterQueue;
use EasyPrint\Domain\Printer\PrinterState;
use EasyPrint\Domain\Printer\QueueSnapshot;
use EasyPrint\Infrastructure\Cups\LpoptionsOutputParser;
use EasyPrint\Tests\Support\FakeQueueCapabilityDiscovery;
use EasyPrint\Tests\Support\FakeQueueDiscovery;

use function mkdir;

use PHPUnit\Framework\TestCase;

use function rmdir;

use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class HomeActionTest extends TestCase
{
    private string $runtimeDirectory;

    /** @var App<null> */
    private App $application;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 3);
        $this->runtimeDirectory = sys_get_temp_dir() . '/easy-print-http-' . uniqid('', true);
        mkdir($this->runtimeDirectory);
        $createApplication = require $root . '/config/bootstrap.php';
        $this->application = $createApplication(
            environment: [
                'APP_ENV' => 'testing',
                'DATABASE_PATH' => $this->runtimeDirectory . '/database/easy-print.sqlite',
                'TEMPORARY_PATH' => $this->runtimeDirectory . '/temporary',
            ],
            projectRoot: $root,
            queueDiscovery: new FakeQueueDiscovery($this->snapshot()),
            queueCapabilityDiscovery: new FakeQueueCapabilityDiscovery([$this->capabilities()]),
        );
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

    public function testTheFirstRouteReturnsServerRenderedPortugueseHtml(): void
    {
        $request = new ServerRequestFactory()->createServerRequest('GET', '/');

        $response = $this->application->handle($request);
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('<html lang="pt-BR">', $body);
        self::assertStringContainsString('Easy Print está pronto para começar', $body);
        self::assertStringContainsString('Configuração válida', $body);
        self::assertStringContainsString('REFERENCE_QUEUE', $body);
        self::assertStringContainsString('Imprimir um documento', $body);
        self::assertStringContainsString('name="document"', $body);
        self::assertStringContainsString('PageSize', $body);
        self::assertStringNotContainsString('VendorSecret', $body);
        self::assertStringContainsString('hx-get="/print-form?lang=pt-BR"', $body);
        self::assertStringContainsString('/assets/htmx.min.js', $body);
        self::assertStringContainsString('hx-trigger="load"', $body);
        self::assertStringContainsString('/jobs/active?lang=pt-BR', $body);
        self::assertStringContainsString('/history?lang=pt-BR', $body);
        self::assertStringContainsString('/printer/status?lang=pt-BR&amp;queue=REFERENCE_QUEUE', $body);
        self::assertStringContainsString('Pronta', $body);
        self::assertStringContainsString('easy_print_queue=REFERENCE_QUEUE', $response->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('easy_print_session=', $response->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('HttpOnly; SameSite=Strict', $response->getHeaderLine('Set-Cookie'));
        self::assertSame("default-src 'self'; base-uri 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; img-src 'self' data:; object-src 'none'; script-src 'self'; style-src 'self'", $response->getHeaderLine('Content-Security-Policy'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
    }

    public function testTheHealthPageCanBeRenderedFromTheEnglishCatalog(): void
    {
        $request = new ServerRequestFactory()
            ->createServerRequest('GET', '/?lang=en')
            ->withQueryParams(['lang' => 'en']);

        $response = $this->application->handle($request);
        $body = (string) $response->getBody();

        self::assertStringContainsString('<html lang="en">', $body);
        self::assertStringContainsString('Easy Print is ready to begin', $body);
        self::assertStringContainsString('Valid configuration', $body);
        self::assertStringContainsString('Ready', $body);
    }

    public function testARequestedQueueIsValidatedSelectedEscapedAndPersisted(): void
    {
        $request = new ServerRequestFactory()
            ->createServerRequest('GET', '/?queue=%3Cunsafe%3E')
            ->withQueryParams(['queue' => '<unsafe>']);

        $response = $this->application->handle($request);
        $body = (string) $response->getBody();

        self::assertStringNotContainsString('<unsafe>', $body);
        self::assertStringContainsString('&lt;unsafe&gt;', $body);
        self::assertStringContainsString('easy_print_queue=%3Cunsafe%3E', $response->getHeaderLine('Set-Cookie'));
    }

    public function testAValidCookieSurvivesNavigationWithoutBeingRewritten(): void
    {
        $request = new ServerRequestFactory()
            ->createServerRequest('GET', '/')
            ->withCookieParams(['easy_print_queue' => '<unsafe>']);

        $response = $this->application->handle($request);
        $body = (string) $response->getBody();

        self::assertStringContainsString('&lt;unsafe&gt;', $body);
        self::assertStringNotContainsString('easy_print_queue=', $response->getHeaderLine('Set-Cookie'));
    }

    public function testThePinnedHtmxAssetIsAvailableThroughTheApplicationRouter(): void
    {
        $request = new ServerRequestFactory()->createServerRequest('GET', '/assets/htmx.min.js');

        $response = $this->application->handle($request);
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/javascript; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('htmx', $body);
        self::assertSame((string) strlen($body), $response->getHeaderLine('Content-Length'));
    }

    public function testThePrintFormCanBeRefreshedForASelectedQueueWithHtmx(): void
    {
        $request = new ServerRequestFactory()
            ->createServerRequest('GET', '/print-form?queue=REFERENCE_QUEUE')
            ->withQueryParams(['queue' => 'REFERENCE_QUEUE']);

        $response = $this->application->handle($request);
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('id="print-form"', $body);
        self::assertStringContainsString('hx-target="#print-form"', $body);
        self::assertStringContainsString('name="capability_fingerprint"', $body);
        self::assertStringContainsString('name="submission_key"', $body);
        self::assertStringContainsString('PageSize', $body);
        self::assertStringNotContainsString('VendorSecret', $body);
    }

    private function snapshot(): QueueSnapshot
    {
        return new QueueSnapshot(
            connectivity: CupsConnectivity::Available,
            queues: [
                new PrinterQueue('REFERENCE_QUEUE', PrinterState::Ready),
                new PrinterQueue('<unsafe>', PrinterState::Unknown),
            ],
            defaultQueueIdentifier: 'REFERENCE_QUEUE',
        );
    }

    private function capabilities(): CapabilitySnapshot
    {
        $parser = new LpoptionsOutputParser();
        $options = $parser->parse("PageSize/Media Size: *A4 Letter\nVendorSecret/Diagnostic: *Off On\n");

        return new CapabilitySnapshot(
            queueIdentifier: 'REFERENCE_QUEUE',
            connectivity: CupsConnectivity::Available,
            options: $options,
            fingerprint: $parser->fingerprint($options),
        );
    }
}
