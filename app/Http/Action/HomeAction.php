<?php

declare(strict_types=1);

namespace EasyPrint\Http\Action;

use EasyPrint\Application\Printer\QueueDiscovery;
use EasyPrint\Application\Printer\QueueSelectionResolver;
use EasyPrint\Domain\Printer\PrinterQueue;
use EasyPrint\Http\PrintFormDataFactory;
use EasyPrint\Http\QueueSelectionCookie;
use EasyPrint\Infrastructure\Configuration\AppConfig;
use EasyPrint\Translation\LocaleResolver;
use EasyPrint\Translation\Translator;
use EasyPrint\Views\PhpRenderer;

use function http_build_query;
use function is_string;

use const PHP_QUERY_RFC3986;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class HomeAction
{
    public function __construct(
        private AppConfig $config,
        private QueueDiscovery $queueDiscovery,
        private QueueSelectionResolver $selectionResolver,
        private QueueSelectionCookie $selectionCookie,
        private PrintFormDataFactory $printFormFactory,
        private LocaleResolver $localeResolver,
        private Translator $translator,
        private PhpRenderer $renderer,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $locale = $this->localeResolver->resolve($request);
        $t = fn(string $key): string => $this->translator->translate($locale, $key);
        $snapshot = $this->queueDiscovery->discover();
        $requestedQueue = $request->getQueryParams()['queue'] ?? null;
        $selection = $this->selectionResolver->resolve(
            snapshot: $snapshot,
            requested: is_string($requestedQueue) ? $requestedQueue : null,
            persisted: $this->selectionCookie->read($request),
        );
        $queues = array_map(fn(PrinterQueue $queue): array => [
            'identifier' => $queue->identifier,
            'stateLabel' => $t('printer.state.' . $queue->state->value),
            'selected' => $queue->identifier === $selection->queue?->identifier,
            'default' => $queue->identifier === $snapshot->defaultQueueIdentifier,
            'href' => $this->config->basePath . '/?' . http_build_query([
                'lang' => $locale,
                'queue' => $queue->identifier,
            ], encoding_type: PHP_QUERY_RFC3986),
        ], $snapshot->queues);

        $response = $this->renderer->render($response, 'home', [
            'locale' => $locale,
            'pageTitle' => $t('home.page_title'),
            'heading' => $t('home.heading'),
            'description' => $t('home.description'),
            'statusLabel' => $t('home.status_label'),
            'statusValue' => $t('home.status_ready'),
            'environmentLabel' => $t('home.environment_label'),
            'environmentValue' => $t('environment.' . $this->config->environment),
            'cupsLabel' => $t('home.cups_label'),
            'cupsValue' => $t('cups.connectivity.' . $snapshot->connectivity->value),
            'queuesHeading' => $t('printer.queues_heading'),
            'noQueuesMessage' => $t('printer.no_queues'),
            'selectedLabel' => $t('printer.selected'),
            'defaultLabel' => $t('printer.default'),
            'queues' => $queues,
            'languageLabel' => $t('home.language_label'),
            'portugueseLabel' => $t('locale.pt-BR'),
            'englishLabel' => $t('locale.en'),
            'stylesheetUrl' => $this->config->basePath . '/assets/app.css',
            'htmxAssetUrl' => $this->config->basePath . '/assets/htmx.min.js',
            'activeJobsUrl' => $this->config->basePath . '/jobs/active?lang=' . rawurlencode($locale),
            'activeJobsHeading' => $t('jobs.heading'),
            'activeJobsLoading' => $t('jobs.loading'),
            'historyUrl' => $this->config->basePath . '/history?lang=' . rawurlencode($locale),
            'historyLabel' => $t('history.open'),
            'printerStatusUrl' => null === $selection->queue
                ? null
                : $this->config->basePath . '/printer/status?' . http_build_query([
                    'lang' => $locale,
                    'queue' => $selection->queue->identifier,
                ], encoding_type: PHP_QUERY_RFC3986),
            'printerStatusHeading' => $t('printer_status.heading'),
            'printerStatusLoading' => $t('printer_status.loading'),
            'printerStatusNoSelection' => $t('printer_status.no_selection'),
            'printFormHtml' => $this->renderer->renderString(
                'print-form',
                $this->printFormFactory->create($request)['data'],
            ),
        ]);

        return $this->selectionCookie->apply($response, $selection);
    }
}
