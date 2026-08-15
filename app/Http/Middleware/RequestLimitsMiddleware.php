<?php

declare(strict_types=1);

namespace EasyPrint\Http\Middleware;

use function count;
use function ctype_digit;

use InvalidArgumentException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function strlen;

final readonly class RequestLimitsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ResponseFactoryInterface $responses,
        private int $maximumBodyBytes,
        private int $maximumHeaderBytes,
    ) {
        if ($maximumBodyBytes < 1 || $maximumHeaderBytes < 1) {
            throw new InvalidArgumentException('Request limits must be positive integers.');
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->headerBytes($request) > $this->maximumHeaderBytes) {
            return $this->rejectedResponse(431);
        }

        $contentLengths = $request->getHeader('Content-Length');

        if (count($contentLengths) > 1 || (isset($contentLengths[0]) && !ctype_digit($contentLengths[0]))) {
            return $this->rejectedResponse(400);
        }

        if (isset($contentLengths[0]) && (int) $contentLengths[0] > $this->maximumBodyBytes) {
            return $this->rejectedResponse(413);
        }

        $knownBodySize = $request->getBody()->getSize();

        if (null !== $knownBodySize && $knownBodySize > $this->maximumBodyBytes) {
            return $this->rejectedResponse(413);
        }

        return $handler->handle($request);
    }

    private function headerBytes(ServerRequestInterface $request): int
    {
        $bytes = 2;

        foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $bytes += strlen($name) + 2 + strlen($value) + 2;
            }
        }

        return $bytes;
    }

    private function rejectedResponse(int $status): ResponseInterface
    {
        $response = $this->responses->createResponse($status)
            ->withHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->getBody()->write('Request rejected.');

        return $response;
    }
}
