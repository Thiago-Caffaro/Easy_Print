<?php

declare(strict_types=1);

namespace EasyPrint\Http\Action;

use function file_get_contents;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function strlen;

final readonly class StaticAssetAction
{
    public function __construct(
        private string $path,
        private string $contentType,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $contents = file_get_contents($this->path);

        if (false === $contents) {
            throw new RuntimeException('A required static asset could not be read.');
        }

        $response->getBody()->write($contents);

        return $response
            ->withHeader('Content-Type', $this->contentType)
            ->withHeader('Content-Length', (string) strlen($contents));
    }
}
