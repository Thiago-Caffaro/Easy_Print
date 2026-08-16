<?php

declare(strict_types=1);

namespace EasyPrint\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use Throwable;

final readonly class ExceptionLoggingMiddleware implements MiddlewareInterface
{
    public function __construct(private LoggerInterface $logger) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $exception) {
            $status = $exception instanceof HttpException ? $exception->getCode() : 500;
            $context = [
                'method' => $request->getMethod(),
                'status' => $status,
                'exception' => $exception,
            ];

            if ($status < 500) {
                $this->logger->notice('http.request.rejected', $context);
            } else {
                $this->logger->error('http.request.failed', $context);
            }

            throw $exception;
        }
    }
}
