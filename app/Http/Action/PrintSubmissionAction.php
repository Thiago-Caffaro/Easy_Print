<?php

declare(strict_types=1);

namespace EasyPrint\Http\Action;

use Closure;
use EasyPrint\Application\Document\ImageUploadResult;
use EasyPrint\Application\Document\PdfUploadResult;
use EasyPrint\Application\Printer\PrintArgumentMapper;
use EasyPrint\Application\Printer\PrintDocument;
use EasyPrint\Application\Printer\PrintJobState;
use EasyPrint\Application\Printer\PrintRequestInput;
use EasyPrint\Application\Printer\PrintSubmissionInput;
use EasyPrint\Application\Printer\QueueCapabilityDiscovery;
use EasyPrint\Application\Printer\QueueDiscovery;
use EasyPrint\Http\Middleware\CorrelationIdMiddleware;
use EasyPrint\Infrastructure\Configuration\AppConfig;
use EasyPrint\Infrastructure\Upload\SecureImageUpload;
use EasyPrint\Infrastructure\Upload\SecurePdfUpload;
use EasyPrint\Translation\LocaleResolver;
use EasyPrint\Translation\Translator;
use EasyPrint\Views\PhpRenderer;

use function in_array;
use function is_array;
use function is_string;
use function pathinfo;

use const PATHINFO_EXTENSION;

use function preg_match;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

use function rawurlencode;
use function strtolower;

final readonly class PrintSubmissionAction
{
    /** @var Closure(): \EasyPrint\Application\Printer\PrintSubmissionService */
    private Closure $submissionFactory;

    public function __construct(
        private AppConfig $config,
        private QueueDiscovery $queues,
        private QueueCapabilityDiscovery $capabilities,
        private PrintArgumentMapper $arguments,
        private SecurePdfUpload $pdfUpload,
        private SecureImageUpload $imageUpload,
        Closure $submissionFactory,
        private LocaleResolver $localeResolver,
        private Translator $translator,
        private PhpRenderer $renderer,
    ) {
        $this->submissionFactory = $submissionFactory;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $locale = $this->localeResolver->resolve($request);
        $t = fn(string $key): string => $this->translator->translate($locale, $key);
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $queue = $this->string($body['queue'] ?? null);
        $fingerprint = $this->string($body['capability_fingerprint'] ?? null);
        $copies = $this->string($body['copies'] ?? null);
        $pageRange = $this->string($body['page_range'] ?? null);
        $options = is_array($body['options'] ?? null) ? $body['options'] : [];
        $submissionKey = $this->string($body['submission_key'] ?? null);
        $queues = $this->queues->discover();

        if (!$queues->contains($queue) || 1 !== preg_match('/^[A-Za-z0-9_-]{32,128}$/D', $submissionKey)) {
            return $this->result($response, $locale, $t('print.request_invalid'), 422, null, null);
        }

        $mapped = $this->arguments->map(
            $queues,
            $this->capabilities->discover($queue),
            new PrintRequestInput($queue, $fingerprint, $copies, $pageRange, $options),
        );

        $validated = $mapped->validated;

        if (null === $validated) {
            return $this->result($response, $locale, $t('print.request_invalid'), 422, null, null);
        }

        $upload = $request->getUploadedFiles()['document'] ?? null;

        if (!$upload instanceof UploadedFileInterface) {
            return $this->result($response, $locale, $t('upload.error.missing'), 422, null, null);
        }

        $document = $this->store($upload);

        if ($document instanceof PdfUploadResult) {
            if (!$document->succeeded()) {
                return $this->result(
                    $response,
                    $locale,
                    $t($document->failure?->translationKey() ?? 'upload.error.upload_failed'),
                    422,
                    null,
                    null,
                );
            }

            $stored = $document->document;
            if (null === $stored) {
                return $this->result($response, $locale, $t('upload.error.upload_failed'), 422, null, null);
            }

            $document = PrintDocument::fromStoredPdf($stored);
        } elseif ($document instanceof ImageUploadResult) {
            if (!$document->succeeded()) {
                return $this->result(
                    $response,
                    $locale,
                    $t($document->failure?->translationKey() ?? 'image_upload.error.upload_failed'),
                    422,
                    null,
                    null,
                );
            }

            $stored = $document->document;
            if (null === $stored) {
                return $this->result($response, $locale, $t('image_upload.error.upload_failed'), 422, null, null);
            }

            $document = PrintDocument::fromStoredImage($stored);
        }

        $correlationId = $request->getAttribute(CorrelationIdMiddleware::ATTRIBUTE);
        $correlationId = is_string($correlationId) ? $correlationId : 'unknown';
        $submitted = ($this->submissionFactory)()->submit(new PrintSubmissionInput(
            submissionKey: $submissionKey,
            correlationId: $correlationId,
            cupsServerKey: $this->config->cupsServerKey,
            document: $document,
            arguments: $validated,
        ));

        $status = $submitted->record->state;
        $message = match ($status) {
            PrintJobState::Accepted => $t('print.submitted'),
            PrintJobState::Indeterminate => $t('print.submission_pending'),
            default => $t('print.submission_failed'),
        };

        return $this->result(
            $response,
            $locale,
            $message,
            PrintJobState::Failed === $status ? 502 : 202,
            $submitted->record->cupsJobId,
            $submitted->record->safeErrorCode,
        );
    }

    private function store(UploadedFileInterface $upload): PdfUploadResult|ImageUploadResult
    {
        $name = $upload->getClientFilename();
        $extension = is_string($name) ? strtolower(pathinfo($name, PATHINFO_EXTENSION)) : '';

        return in_array($extension, ['png', 'jpg', 'jpeg'], true)
            ? $this->imageUpload->store($upload)
            : $this->pdfUpload->store($upload);
    }

    private function result(
        ResponseInterface $response,
        string $locale,
        string $message,
        int $status,
        ?int $cupsJobId,
        ?string $safeErrorCode,
    ): ResponseInterface {
        return $this->renderer->render($response->withStatus($status), 'print-result', [
            'locale' => $locale,
            'pageTitle' => $this->translator->translate($locale, 'print.result_title'),
            'heading' => $this->translator->translate($locale, 'print.result_heading'),
            'message' => $message,
            'cupsJobId' => $cupsJobId,
            'jobIdLabel' => $this->translator->translate($locale, 'print.job_id'),
            'error' => $safeErrorCode,
            'backUrl' => $this->config->basePath . '/?lang=' . rawurlencode($locale),
            'backLabel' => $this->translator->translate($locale, 'print.back'),
            'historyUrl' => $this->config->basePath . '/history?lang=' . rawurlencode($locale),
            'historyLabel' => $this->translator->translate($locale, 'history.open'),
            'stylesheetUrl' => $this->config->basePath . '/assets/app.css',
        ]);
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
