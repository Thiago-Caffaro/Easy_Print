<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

use function bin2hex;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

use function is_file;
use function preg_match;
use function random_bytes;
use function sprintf;

use Throwable;

use function unlink;

final readonly class PrintSubmissionService
{
    public function __construct(
        private PrintJobRepository $repository,
        private CupsJobSubmitter $submitter,
        private int $historyRetentionDays,
    ) {
        if ($historyRetentionDays < 1 || $historyRetentionDays > 3_650) {
            throw new InvalidArgumentException('History retention must be between 1 and 3650 days.');
        }
    }

    public function submit(PrintSubmissionInput $input): PrintSubmissionResult
    {
        if (1 !== preg_match('/^[A-Za-z0-9_-]{32,128}$/D', $input->submissionKey)) {
            $this->deleteTemporaryFile($input->document->absolutePath);

            throw new InvalidArgumentException('The submission key is invalid.');
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $timestamp = $now->format('Y-m-d\TH:i:s\Z');
        $draft = new PrintJobDraft(
            id: bin2hex(random_bytes(16)),
            correlationId: $input->correlationId,
            cupsServerKey: $input->cupsServerKey,
            queueName: $input->arguments->queueIdentifier,
            originalName: $input->document->originalName,
            mediaType: $input->document->mediaType,
            byteSize: $input->document->byteSize,
            copies: $input->arguments->copies,
            pageRange: $input->arguments->pageRange,
            selectedOptions: $input->arguments->selectedOptions,
            submittedAt: $timestamp,
            retainedUntil: $now->modify(sprintf('+%d days', $this->historyRetentionDays))->format('Y-m-d\TH:i:s\Z'),
        );

        try {
            $reservation = $this->repository->reserve($input->submissionKey, $draft);
        } catch (Throwable $exception) {
            $this->deleteTemporaryFile($input->document->absolutePath);

            throw $exception;
        }

        if (!$reservation->created) {
            $deleted = $this->deleteTemporaryFile($input->document->absolutePath);

            return new PrintSubmissionResult($reservation->record, true, $deleted);
        }

        try {
            $this->repository->markSubmitting($draft->id, $timestamp);
        } catch (Throwable $exception) {
            $this->deleteTemporaryFile($input->document->absolutePath);

            throw $exception;
        }

        try {
            $submission = $this->submitter->submit($input->arguments, $input->document);
        } catch (Throwable) {
            $submission = CupsJobSubmission::failed(
                'submission_internal_error',
                'adapter=exception',
            );
        }

        $deleted = $this->deleteTemporaryFile($input->document->absolutePath);
        $completedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        $record = $this->repository->finishSubmission($draft->id, $submission, $deleted, $completedAt);

        return new PrintSubmissionResult($record, false, $deleted);
    }

    private function deleteTemporaryFile(string $path): bool
    {
        return !is_file($path) || @unlink($path);
    }
}
