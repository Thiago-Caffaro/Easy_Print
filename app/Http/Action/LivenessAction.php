<?php

declare(strict_types=1);

namespace EasyPrint\Http\Action;

use EasyPrint\Application\Health\HealthStatus;

use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class LivenessAction
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write(json_encode([
            'status' => HealthStatus::Ok->value,
            'checks' => ['application' => HealthStatus::Ok->value],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $response->withHeader('Content-Type', 'application/json; charset=UTF-8');
    }
}
