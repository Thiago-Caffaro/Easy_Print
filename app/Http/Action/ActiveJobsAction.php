<?php

declare(strict_types=1);

namespace EasyPrint\Http\Action;

use EasyPrint\Application\Printer\ActiveJobDiscovery;
use EasyPrint\Application\Printer\JobTitleLookup;
use EasyPrint\Domain\Printer\ActivePrintJob;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Http\Middleware\CsrfProtectionMiddleware;
use EasyPrint\Infrastructure\Configuration\AppConfig;
use EasyPrint\Translation\LocaleResolver;
use EasyPrint\Translation\Translator;
use EasyPrint\Views\PhpRenderer;

use function in_array;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function rawurlencode;
use function round;
use function sprintf;

final readonly class ActiveJobsAction
{
    public function __construct(
        private AppConfig $config,
        private ActiveJobDiscovery $discovery,
        private JobTitleLookup $titleLookup,
        private LocaleResolver $localeResolver,
        private Translator $translator,
        private PhpRenderer $renderer,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $locale = $this->localeResolver->resolve($request);
        $t = fn(string $key): string => $this->translator->translate($locale, $key);
        $snapshot = $this->discovery->discover();
        $cancelNotice = match ($request->getQueryParams()['cancel'] ?? null) {
            'cancelled' => $t('jobs.cancelled'),
            'not-found' => $t('jobs.cancel_not_found'),
            'not-cancelable' => $t('jobs.cancel_not_cancelable'),
            'unavailable' => $t('jobs.cancel_unavailable'),
            'failed' => $t('jobs.cancel_failed'),
            default => null,
        };
        $url = $this->config->basePath . '/jobs/active?lang=' . rawurlencode($locale);
        $jobs = array_map(fn(ActivePrintJob $job): array => [
            'cupsJobId' => (string) $job->cupsJobId,
            'queueIdentifier' => $job->queueIdentifier,
            'title' => $this->titleLookup->findOriginalName(
                $this->config->cupsServerKey,
                $job->queueIdentifier,
                $job->cupsJobId,
            ) ?? $t('jobs.external_title'),
            'submittedAt' => $job->submittedAtLabel,
            'byteSize' => $this->formatBytes($job->byteSize, $locale),
            'stateLabel' => $t('jobs.state.' . $job->state->value),
            'cancelable' => in_array($job->state->value, ['pending', 'processing'], true),
            'cancelUrl' => $this->config->basePath . '/jobs/' . rawurlencode($job->queueIdentifier)
                . '/cancel/' . $job->cupsJobId . '?lang=' . rawurlencode($locale),
        ], $snapshot->jobs);
        $available = CupsConnectivity::Available === $snapshot->connectivity;
        $pollTrigger = !$available ? 'every 15s' : ([] === $jobs ? null : 'every 3s');

        return $this->renderer->render($response, 'active-jobs', [
            'heading' => $t('jobs.heading'),
            'connectivityLabel' => $t('cups.connectivity.' . $snapshot->connectivity->value),
            'errorMessage' => $t('jobs.unavailable'),
            'emptyMessage' => $t('jobs.empty'),
            'refreshLabel' => $t('jobs.refresh'),
            'jobIdLabel' => $t('jobs.job_id'),
            'queueLabel' => $t('jobs.queue'),
            'submittedAtLabel' => $t('jobs.submitted_at'),
            'sizeLabel' => $t('jobs.size'),
            'stateLabel' => $t('jobs.state_label'),
            'available' => $available,
            'jobs' => $jobs,
            'pollUrl' => $url,
            'pollTrigger' => $pollTrigger,
            'csrfToken' => $request->getAttribute(CsrfProtectionMiddleware::TOKEN_ATTRIBUTE),
            'cancelLabel' => $t('jobs.cancel'),
            'cancelConfirm' => $t('jobs.cancel_confirm'),
            'cancelNotice' => $cancelNotice,
        ]);
    }

    private function formatBytes(int $bytes, string $locale): string
    {
        if ($bytes < 1_024) {
            return sprintf('%d B', $bytes);
        }

        if ($bytes < 1_048_576) {
            return number_format(round($bytes / 1_024, 1), 1, 'pt-BR' === $locale ? ',' : '.', '') . ' KB';
        }

        return number_format(round($bytes / 1_048_576, 1), 1, 'pt-BR' === $locale ? ',' : '.', '') . ' MB';
    }
}
