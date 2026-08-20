<?php

declare(strict_types=1);

namespace EasyPrint\Http\Action;

use function ctype_digit;

use DateTimeImmutable;
use DateTimeZone;
use EasyPrint\Application\Printer\PrintHistoryEntry;
use EasyPrint\Application\Printer\PrintHistoryReader;
use EasyPrint\Infrastructure\Configuration\AppConfig;
use EasyPrint\Translation\LocaleResolver;
use EasyPrint\Translation\Translator;
use EasyPrint\Views\PhpRenderer;

use function implode;

use IntlDateFormatter;

use function is_string;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function rawurlencode;
use function round;
use function sprintf;

final readonly class PrintHistoryAction
{
    private const int ITEMS_PER_PAGE = 20;

    public function __construct(
        private AppConfig $config,
        private PrintHistoryReader $history,
        private LocaleResolver $localeResolver,
        private Translator $translator,
        private PhpRenderer $renderer,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $locale = $this->localeResolver->resolve($request);
        /** @param array<string,scalar> $parameters */
        $t = fn(string $key, array $parameters = []): string => $this->translator->translate($locale, $key, $parameters);
        $requestedPage = $request->getQueryParams()['page'] ?? null;
        $pageNumber = is_string($requestedPage) && ctype_digit($requestedPage) ? (int) $requestedPage : 1;
        $page = $this->history->readPage($pageNumber, self::ITEMS_PER_PAGE);
        $entries = array_map(fn(PrintHistoryEntry $entry): array => [
            'title' => $entry->originalName ?? $t('history.unknown_document'),
            'queue' => $entry->queueName,
            'jobId' => null === $entry->cupsJobId ? $t('history.not_assigned') : (string) $entry->cupsJobId,
            'type' => $t('history.type.' . $entry->mediaType),
            'size' => $this->formatBytes($entry->byteSize, $locale),
            'copies' => (string) $entry->copies,
            'pages' => $entry->pageRange ?? $t('history.all_pages'),
            'options' => $this->formatOptions($entry->selectedOptions, $t('history.default_options')),
            'state' => $t('history.state.' . $entry->state->value),
            'submittedAt' => $this->formatDate($entry->submittedAt, $locale),
            'error' => null === $entry->safeErrorCode
                ? null
                : $t('history.safe_error') . ' (' . $entry->safeErrorCode . ')',
        ], $page->entries);

        return $this->renderer->render($response, 'print-history', [
            'locale' => $locale,
            'pageTitle' => $t('history.page_title'),
            'heading' => $t('history.heading'),
            'description' => $t('history.description'),
            'backLabel' => $t('history.back'),
            'backUrl' => $this->config->basePath . '/?lang=' . rawurlencode($locale),
            'stylesheetUrl' => $this->config->basePath . '/assets/app.css',
            'available' => $page->available,
            'emptyMessage' => $t('history.empty'),
            'unavailableMessage' => $t('history.unavailable'),
            'entries' => $entries,
            'labels' => [
                'queue' => $t('history.queue'),
                'jobId' => $t('history.job_id'),
                'type' => $t('history.type_label'),
                'size' => $t('history.size'),
                'copies' => $t('history.copies'),
                'pages' => $t('history.pages'),
                'options' => $t('history.options'),
                'state' => $t('history.state_label'),
                'submittedAt' => $t('history.submitted_at'),
            ],
            'previousUrl' => $page->page > 1 ? $this->pageUrl($locale, $page->page - 1) : null,
            'nextUrl' => $page->page < $page->totalPages() ? $this->pageUrl($locale, $page->page + 1) : null,
            'previousLabel' => $t('history.previous'),
            'nextLabel' => $t('history.next'),
            'pageLabel' => $t('history.page', ['current' => $page->page, 'total' => $page->totalPages()]),
        ]);
    }

    private function pageUrl(string $locale, int $page): string
    {
        return sprintf('%s/history?lang=%s&page=%d', $this->config->basePath, rawurlencode($locale), $page);
    }

    private function formatDate(string $timestamp, string $locale): string
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $timestamp, new DateTimeZone('UTC'));

        if (false === $date) {
            return $timestamp;
        }

        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, 'UTC');
        $formatted = $formatter->format($date);

        return false === $formatted ? $timestamp : $formatted . ' UTC';
    }

    /** @param array<string,string> $options */
    private function formatOptions(array $options, string $empty): string
    {
        if ([] === $options) {
            return $empty;
        }

        $formatted = [];

        foreach ($options as $name => $value) {
            $formatted[] = sprintf('%s: %s', $name, $value);
        }

        return implode(', ', $formatted);
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
