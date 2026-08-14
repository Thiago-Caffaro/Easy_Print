<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Integration\Http;

use function dirname;
use function mkdir;

use PHPUnit\Framework\TestCase;

use function rmdir;

use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

use function sys_get_temp_dir;
use function uniqid;

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
        $this->application = $createApplication([
            'APP_ENV' => 'testing',
            'DATABASE_PATH' => $this->runtimeDirectory . '/database/easy-print.sqlite',
            'TEMPORARY_PATH' => $this->runtimeDirectory . '/temporary',
        ], $root);
    }

    protected function tearDown(): void
    {
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
    }
}
