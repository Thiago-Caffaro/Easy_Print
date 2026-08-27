<?php

declare(strict_types=1);

namespace EasyPrint\Http;

use function bin2hex;

use EasyPrint\Application\Printer\QueueCapabilityDiscovery;
use EasyPrint\Application\Printer\QueueDiscovery;
use EasyPrint\Application\Printer\QueueSelection;
use EasyPrint\Application\Printer\QueueSelectionResolver;
use EasyPrint\Domain\Printer\CapabilityCategory;
use EasyPrint\Domain\Printer\CapabilityOption;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Domain\Printer\PrinterQueue;
use EasyPrint\Infrastructure\Configuration\AppConfig;
use EasyPrint\Translation\LocaleResolver;
use EasyPrint\Translation\Translator;

use function in_array;
use function is_string;

use Psr\Http\Message\ServerRequestInterface;

use function random_bytes;
use function rawurlencode;

final readonly class PrintFormDataFactory
{
    public function __construct(
        private AppConfig $config,
        private QueueDiscovery $queues,
        private QueueCapabilityDiscovery $capabilities,
        private QueueSelectionResolver $selectionResolver,
        private QueueSelectionCookie $selectionCookie,
        private LocaleResolver $localeResolver,
        private Translator $translator,
    ) {}

    /**
     * @return array{selection:QueueSelection,data:array<string,mixed>}
     */
    public function create(ServerRequestInterface $request): array
    {
        $locale = $this->localeResolver->resolve($request);
        $t = fn(string $key): string => $this->translator->translate($locale, $key);
        $snapshot = $this->queues->discover();
        $requestedQueue = $request->getQueryParams()['queue'] ?? null;
        $selection = $this->selectionResolver->resolve(
            snapshot: $snapshot,
            requested: is_string($requestedQueue) ? $requestedQueue : null,
            persisted: $this->selectionCookie->read($request),
        );
        $capabilities = null === $selection->queue
            ? null
            : $this->capabilities->discover($selection->queue->identifier);
        $available = null !== $selection->queue
            && null !== $capabilities
            && $selection->queue->identifier === $capabilities->queueIdentifier
            && CupsConnectivity::Available === $capabilities->connectivity;

        return [
            'selection' => $selection,
            'data' => [
                'locale' => $locale,
                'queues' => array_map(fn(PrinterQueue $queue): array => [
                    'identifier' => $queue->identifier,
                    'selected' => $queue->identifier === $selection->queue?->identifier,
                ], $snapshot->queues),
                'selectedQueue' => $selection->queue?->identifier,
                'capabilityFingerprint' => $capabilities?->fingerprint,
                'available' => $available,
                'basicOptions' => $available ? $this->options($capabilities->renderableOptions(), false, $t) : [],
                'advancedOptions' => $available ? $this->options($capabilities->renderableOptions(), true, $t) : [],
                'formUrl' => $this->config->basePath . '/print',
                'refreshUrl' => $this->config->basePath . '/print-form?lang=' . rawurlencode($locale),
                'csrfToken' => $request->getAttribute('easy_print.csrf_token'),
                'submissionKey' => bin2hex(random_bytes(32)),
                'labels' => [
                    'heading' => $t('print.heading'),
                    'queue' => $t('print.queue'),
                    'document' => $t('print.document'),
                    'document_hint' => $t('print.document_hint'),
                    'document_empty' => $t('print.document_empty'),
                    'document_preview' => $t('print.document_preview'),
                    'quick_options' => $t('print.quick_options'),
                    'advanced' => $t('print.advanced'),
                    'advanced_hint' => $t('print.advanced_hint'),
                    'paper_options' => $t('print.paper_options'),
                    'preview_empty' => $t('print.preview_empty'),
                    'copies' => $t('print.copies'),
                    'page_range' => $t('print.page_range'),
                    'page_range_hint' => $t('print.page_range_hint'),
                    'submit' => $t('print.submit'),
                    'no_queue' => $t('print.no_queue'),
                    'capabilities_unavailable' => $t('print.capabilities_unavailable'),
                    'default' => $t('print.default'),
                ],
            ],
        ];
    }

    /**
     * @param list<CapabilityOption> $options
     * @return list<array{identifier:string,label:string,choices:list<string>,default:?string}>
     */
    private function options(array $options, bool $advanced, callable $translate): array
    {
        $result = [];

        foreach ($options as $option) {
            $isAdvanced = !in_array($option->category, [
                CapabilityCategory::MediaSize,
                CapabilityCategory::MediaType,
                CapabilityCategory::ColorMode,
                CapabilityCategory::Quality,
            ], true);

            if ($isAdvanced !== $advanced) {
                continue;
            }

            $result[] = [
                'identifier' => $option->technicalIdentifier,
                'label' => $translate('print.option.' . $option->category->value),
                'choices' => array_map(static fn($choice): string => $choice->technicalIdentifier, $option->choices),
                'default' => $option->defaultTechnicalIdentifier,
            ];
        }

        return $result;
    }
}
