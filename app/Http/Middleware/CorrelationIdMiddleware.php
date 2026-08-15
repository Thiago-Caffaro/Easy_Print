<?php

declare(strict_types=1);

namespace EasyPrint\Http\Middleware;

use function bin2hex;

use EasyPrint\Infrastructure\Observability\CorrelationContext;

use function hrtime;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

use function random_bytes;
use function str_ends_with;

use Throwable;

final readonly class CorrelationIdMiddleware implements MiddlewareInterface
{
    public const ATTRIBUTE = 'easy_print.correlation_id';

    public function __construct(
        private CorrelationContext $context,
        private LoggerInterface $logger,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $correlationId = bin2hex(random_bytes(16));
        $startedAt = hrtime(true);
        $this->context->begin($correlationId);

        try {
            $response = $handler->handle($request->withAttribute(self::ATTRIBUTE, $correlationId));
            $level = str_ends_with($request->getUri()->getPath(), '/health/live') ? 'debug' : 'info';
            $this->logger->log($level, 'http.request.completed', [
                'method' => $request->getMethod(),
                'status' => $response->getStatusCode(),
                'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
            ]);

            return $response->withHeader('X-Request-ID', $correlationId);
        } catch (Throwable $exception) {
            $this->logger->critical('http.middleware.failed', [
                'method' => $request->getMethod(),
                'exception' => $exception,
            ]);

            throw $exception;
        } finally {
            $this->context->end($correlationId);
        }
    }
}
