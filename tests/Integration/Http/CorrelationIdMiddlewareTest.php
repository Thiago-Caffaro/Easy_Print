<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Integration\Http;

use EasyPrint\Http\Middleware\CorrelationIdMiddleware;
use EasyPrint\Http\Middleware\ExceptionLoggingMiddleware;
use EasyPrint\Infrastructure\Observability\CorrelationContext;
use EasyPrint\Infrastructure\Observability\JsonLineLogger;

use function fclose;
use function fopen;
use function is_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

use PHPUnit\Framework\TestCase;

use function preg_match;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function rewind;

use RuntimeException;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

use function stream_get_contents;

final class CorrelationIdMiddlewareTest extends TestCase
{
    /** @var resource */
    private $stream;

    /** @var App<ContainerInterface|null> */
    private App $application;

    private CorrelationContext $context;

    protected function setUp(): void
    {
        $stream = fopen('php://memory', 'w+b');

        if (false === $stream) {
            self::fail('The in-memory log stream could not be opened.');
        }

        $this->stream = $stream;
        $this->context = new CorrelationContext();
        $logger = new JsonLineLogger($this->context, $this->stream);
        $this->application = AppFactory::create();
        $this->application->get('/id', static function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
            $response->getBody()->write((string) $request->getAttribute(CorrelationIdMiddleware::ATTRIBUTE));

            return $response;
        });
        $this->application->get('/failure', static function (): never {
            throw new RuntimeException('private failure detail');
        });
        $this->application->addRoutingMiddleware();
        $this->application->add(new ExceptionLoggingMiddleware($logger));
        $this->application->addErrorMiddleware(false, false, false);
        $this->application->add(new CorrelationIdMiddleware($this->context, $logger));
    }

    protected function tearDown(): void
    {
        fclose($this->stream);
    }

    public function testItGeneratesPropagatesAndReturnsItsOwnBoundedIdentifier(): void
    {
        $request = new ServerRequestFactory()->createServerRequest('GET', '/id')
            ->withHeader('X-Request-ID', str_repeat('client-controlled-', 100));

        $response = $this->application->handle($request);
        $identifier = $response->getHeaderLine('X-Request-ID');

        self::assertSame(1, preg_match('/^[a-f0-9]{32}$/D', $identifier));
        self::assertSame($identifier, (string) $response->getBody());
        self::assertNull($this->context->current());

        $records = $this->records();
        self::assertCount(1, $records);
        self::assertSame($identifier, $records[0]['correlation_id']);
        self::assertSame('http.request.completed', $records[0]['event']);
    }

    public function testExceptionsAreLoggedWithoutTheirPrivateMessageOrStack(): void
    {
        $response = $this->application->handle(new ServerRequestFactory()->createServerRequest('GET', '/failure'));

        self::assertSame(500, $response->getStatusCode());
        self::assertStringNotContainsString('private failure detail', (string) $response->getBody());
        self::assertSame(1, preg_match('/^[a-f0-9]{32}$/D', $response->getHeaderLine('X-Request-ID')));

        $rawLog = $this->logContents();
        self::assertStringNotContainsString('private failure detail', $rawLog);
        self::assertStringNotContainsString(__FILE__, $rawLog);
        self::assertStringContainsString(RuntimeException::class, $rawLog);
        self::assertStringContainsString('http.request.failed', $rawLog);
        self::assertStringContainsString('http.request.completed', $rawLog);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function records(): array
    {
        return array_map(static function (string $line): array {
            $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

            if (!is_array($record)) {
                self::fail('Expected a structured JSON log record.');
            }

            return $record;
        }, array_values(array_filter(explode("\n", $this->logContents()))));
    }

    private function logContents(): string
    {
        rewind($this->stream);
        $contents = stream_get_contents($this->stream);
        self::assertIsString($contents);

        return $contents;
    }
}
