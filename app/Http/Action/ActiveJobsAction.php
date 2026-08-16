<?php

declare(strict_types=1);

namespace EasyPrint\Http\Action;

use EasyPrint\Application\Printer\ActiveJobDiscovery;
use EasyPrint\Application\Printer\JobTitleLookup;
use EasyPrint\Domain\Printer\ActivePrintJob;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Infrastructure\Configuration\AppConfig;
use EasyPrint\Translation\LocaleResolver;
use EasyPrint\Translation\Translator;
use EasyPrint\Views\PhpRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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
            'byteSize' => $this->formatBytes($job->byteSize),
            'stateLabel' => $t('jobs.state.' . $job->state->value),
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
        ]);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1_024) {
            return sprintf('%d B', $bytes);
        }

        if ($bytes < 1_048_576) {
            return sprintf('%.1f KB', round($bytes / 1_024, 1));
        }

        return sprintf('%.1f MB', round($bytes / 1_048_576, 1));
    }
}
