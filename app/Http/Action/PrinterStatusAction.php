<?php

declare(strict_types=1);

namespace EasyPrint\Http\Action;

use function array_map;

use Closure;
use EasyPrint\Application\Printer\QueueDiscovery;
use EasyPrint\Application\Printer\QueueStatusDiscovery;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Domain\Printer\PrinterQueue;
use EasyPrint\Domain\Printer\PrinterState;
use EasyPrint\Domain\Printer\PrinterStatusSnapshot;
use EasyPrint\Infrastructure\Configuration\AppConfig;
use EasyPrint\Translation\LocaleResolver;
use EasyPrint\Translation\Translator;
use EasyPrint\Views\PhpRenderer;

use function is_string;
use function preg_replace;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function rawurlencode;

final readonly class PrinterStatusAction
{
    private const array KNOWN_REASONS = [
        'media-needed' => 'media_needed',
        'media-empty' => 'media_needed',
        'cover-open' => 'cover_open',
        'paused' => 'paused',
        'offline' => 'offline',
        'timed-out' => 'offline',
        'connecting-to-device' => 'connecting',
        'media-jam' => 'media_jam',
        'processing-to-stop-point' => 'processing',
        'marker-supply-empty' => 'marker_empty',
        'marker-supply-low' => 'marker_low',
    ];

    public function __construct(
        private AppConfig $config,
        private QueueDiscovery $queues,
        private QueueStatusDiscovery $status,
        private LocaleResolver $localeResolver,
        private Translator $translator,
        private PhpRenderer $renderer,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $locale = $this->localeResolver->resolve($request);
        $t = fn(string $key): string => $this->translator->translate($locale, $key);
        $requested = $request->getQueryParams()['queue'] ?? null;
        $queueIdentifier = is_string($requested) ? $requested : '';
        $queues = $this->queues->discover();
        $known = array_filter(
            $queues->queues,
            static fn(PrinterQueue $queue): bool => $queue->identifier === $queueIdentifier,
        );
        $snapshot = CupsConnectivity::Available === $queues->connectivity && [] !== $known
            ? $this->status->discover($queueIdentifier)
            : PrinterStatusSnapshot::failed(
                $queueIdentifier,
                CupsConnectivity::Available === $queues->connectivity
                    ? CupsConnectivity::Unavailable
                    : $queues->connectivity,
            );
        $available = CupsConnectivity::Available === $snapshot->connectivity;
        $pollUrl = $this->config->basePath . '/printer/status?lang=' . rawurlencode($locale)
            . '&queue=' . rawurlencode($queueIdentifier);

        return $this->renderer->render($response, 'printer-status', [
            'heading' => $t('printer_status.heading'),
            'available' => $available,
            'unavailableMessage' => $t('printer_status.unavailable'),
            'queueLabel' => $t('printer_status.queue'),
            'queueIdentifier' => $queueIdentifier,
            'stateLabel' => $t('printer_status.state'),
            'stateValue' => $t('printer.state.' . $snapshot->state->value),
            'acceptingLabel' => $t('printer_status.accepting'),
            'acceptingValue' => $t('printer_status.accepting.' . match ($snapshot->acceptingJobs) {
                true => 'yes',
                false => 'no',
                null => 'unknown',
            }),
            'reasonsHeading' => $t('printer_status.reasons'),
            'readyMessage' => $t('printer_status.ready'),
            'reasons' => array_map(fn(string $reason): string => $this->reasonMessage($reason, $t), $snapshot->reasons),
            'pollUrl' => $pollUrl,
            'pollTrigger' => !$available ? 'every 20s' : (PrinterState::Processing === $snapshot->state ? 'every 5s' : 'every 10s'),
        ]);
    }

    /** @param Closure(string):string $translate */
    private function reasonMessage(string $reason, Closure $translate): string
    {
        $base = preg_replace('/-(error|warning|report)$/D', '', $reason) ?? $reason;
        $known = self::KNOWN_REASONS[$base] ?? null;

        return null === $known
            ? $translate('printer_status.reason.unknown') . ': ' . $reason
            : $translate('printer_status.reason.' . $known);
    }
}
