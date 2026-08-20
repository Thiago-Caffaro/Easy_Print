<?php

declare(strict_types=1);

namespace EasyPrint\Http\Action;

use EasyPrint\Application\Printer\CancelJobResult;
use EasyPrint\Application\Printer\CancelJobStatus;
use EasyPrint\Application\Printer\PrintJobCancellation;
use EasyPrint\Infrastructure\Configuration\AppConfig;
use EasyPrint\Translation\LocaleResolver;

use const FILTER_VALIDATE_INT;

use function filter_var;
use function is_string;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function rawurlencode;

final readonly class CancelJobAction
{
    public function __construct(
        private AppConfig $config,
        private PrintJobCancellation $cancellation,
        private LocaleResolver $localeResolver,
    ) {}

    /** @param array<string,mixed> $args */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $locale = $this->localeResolver->resolve($request);
        $queue = is_string($args['queue'] ?? null) ? $args['queue'] : '';
        $jobId = filter_var($args['job'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $result = false === $jobId
            ? new CancelJobResult(CancelJobStatus::NotFound)
            : $this->cancellation->cancel($queue, $jobId);
        $status = match ($result->status) {
            CancelJobStatus::Cancelled => 'cancelled',
            CancelJobStatus::NotFound => 'not-found',
            CancelJobStatus::NotCancelable => 'not-cancelable',
            CancelJobStatus::Unavailable => 'unavailable',
            CancelJobStatus::Failed => 'failed',
        };
        $location = $this->config->basePath . '/jobs/active?lang=' . rawurlencode($locale)
            . '&cancel=' . $status;

        return $response->withStatus(303)->withHeader('Location', $location);
    }
}
