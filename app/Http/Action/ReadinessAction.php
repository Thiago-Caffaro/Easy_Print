<?php

declare(strict_types=1);

namespace EasyPrint\Http\Action;

use EasyPrint\Application\Health\HealthStatus;
use EasyPrint\Application\Health\ReadinessProbe;

use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ReadinessAction
{
    public function __construct(private ReadinessProbe $probe) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $report = $this->probe->check();
        $status = $report->status();
        $response->getBody()->write(json_encode([
            'status' => $status->value,
            'checks' => $report->checks(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $response
            ->withStatus(HealthStatus::Unavailable === $status ? 503 : 200)
            ->withHeader('Content-Type', 'application/json; charset=UTF-8');
    }
}
