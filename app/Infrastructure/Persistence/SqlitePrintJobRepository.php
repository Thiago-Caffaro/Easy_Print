<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Persistence;

use function bin2hex;

use EasyPrint\Application\Printer\CupsJobSubmission;
use EasyPrint\Application\Printer\PrintHistoryMaintenance;
use EasyPrint\Application\Printer\PrintJobDraft;
use EasyPrint\Application\Printer\PrintJobRecord;
use EasyPrint\Application\Printer\PrintJobRepository;
use EasyPrint\Application\Printer\PrintJobReservation;
use EasyPrint\Application\Printer\PrintJobState;
use InvalidArgumentException;

use function json_encode;

use const JSON_THROW_ON_ERROR;

use PDO;

use function random_bytes;

use RuntimeException;
use Throwable;

final readonly class SqlitePrintJobRepository implements PrintJobRepository, PrintHistoryMaintenance
{
    public function __construct(private PDO $connection) {}

    public function reserve(string $submissionKey, PrintJobDraft $draft): PrintJobReservation
    {
        $existing = $this->findBySubmissionKey($submissionKey);

        if (null !== $existing) {
            return new PrintJobReservation($existing, false);
        }

        $this->connection->beginTransaction();

        try {
            $job = $this->connection->prepare(
                <<<'SQL'
                    INSERT INTO print_jobs (
                        id, correlation_id, cups_server_key, queue_name, original_name,
                        detected_media_type, byte_size, copies, page_range, options_json,
                        state, submitted_at, updated_at, retained_until
                    ) VALUES (
                        :id, :correlation_id, :cups_server_key, :queue_name, :original_name,
                        :detected_media_type, :byte_size, :copies, :page_range, :options_json,
                        :state, :submitted_at, :updated_at, :retained_until
                    )
                    SQL,
            );
            $job->execute([
                'id' => $draft->id,
                'correlation_id' => $draft->correlationId,
                'cups_server_key' => $draft->cupsServerKey,
                'queue_name' => $draft->queueName,
                'original_name' => $draft->originalName,
                'detected_media_type' => $draft->mediaType,
                'byte_size' => $draft->byteSize,
                'copies' => $draft->copies,
                'page_range' => $draft->pageRange,
                'options_json' => json_encode([
                    'version' => 1,
                    'values' => $draft->selectedOptions,
                ], JSON_THROW_ON_ERROR),
                'state' => PrintJobState::Prepared->value,
                'submitted_at' => $draft->submittedAt,
                'updated_at' => $draft->submittedAt,
                'retained_until' => $draft->retainedUntil,
            ]);

            $key = $this->connection->prepare(
                'INSERT INTO print_submission_keys (submission_key, print_job_id, created_at) '
                . 'VALUES (:submission_key, :print_job_id, :created_at)',
            );
            $key->execute([
                'submission_key' => $submissionKey,
                'print_job_id' => $draft->id,
                'created_at' => $draft->submittedAt,
            ]);
            $this->appendEvent($draft->id, PrintJobState::Prepared, null, $draft->submittedAt);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            $existing = $this->findBySubmissionKey($submissionKey);

            if (null !== $existing) {
                return new PrintJobReservation($existing, false);
            }

            throw $exception;
        }

        return new PrintJobReservation($this->findById($draft->id), true);
    }

    public function markSubmitting(string $printJobId, string $updatedAt): PrintJobRecord
    {
        $this->connection->beginTransaction();

        try {
            $statement = $this->connection->prepare(
                'UPDATE print_jobs SET state = :state, updated_at = :updated_at '
                . 'WHERE id = :id AND state = :expected_state',
            );
            $statement->execute([
                'state' => PrintJobState::Submitting->value,
                'updated_at' => $updatedAt,
                'id' => $printJobId,
                'expected_state' => PrintJobState::Prepared->value,
            ]);

            if (1 !== $statement->rowCount()) {
                throw new RuntimeException('The print job could not enter the submitting state.');
            }

            $this->appendEvent($printJobId, PrintJobState::Submitting, null, $updatedAt);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }

        return $this->findById($printJobId);
    }

    public function finishSubmission(
        string $printJobId,
        CupsJobSubmission $submission,
        bool $temporaryFileDeleted,
        string $updatedAt,
    ): PrintJobRecord {
        $safeErrorCode = $submission->safeErrorCode;
        $safeErrorDetail = $submission->safeDiagnostic;

        if (!$temporaryFileDeleted) {
            $safeErrorCode ??= 'temporary_file_cleanup_failed';
            $safeErrorDetail = null === $safeErrorDetail
                ? 'temporary_cleanup=failed'
                : $safeErrorDetail . ';temporary_cleanup=failed';
        }

        $finishedAt = PrintJobState::Failed === $submission->state ? $updatedAt : null;
        $this->connection->beginTransaction();

        try {
            $statement = $this->connection->prepare(
                <<<'SQL'
                    UPDATE print_jobs
                    SET cups_job_id = :cups_job_id,
                        state = :state,
                        safe_error_code = :safe_error_code,
                        safe_error_detail = :safe_error_detail,
                        updated_at = :updated_at,
                        finished_at = :finished_at
                    WHERE id = :id AND state = :expected_state
                    SQL,
            );
            $statement->execute([
                'cups_job_id' => $submission->cupsJobId,
                'state' => $submission->state->value,
                'safe_error_code' => $safeErrorCode,
                'safe_error_detail' => $safeErrorDetail,
                'updated_at' => $updatedAt,
                'finished_at' => $finishedAt,
                'id' => $printJobId,
                'expected_state' => PrintJobState::Submitting->value,
            ]);

            if (1 !== $statement->rowCount()) {
                throw new RuntimeException('The print submission could not be finalized.');
            }

            $this->appendEvent($printJobId, $submission->state, $safeErrorCode, $updatedAt);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }

        return $this->findById($printJobId);
    }

    public function reconcile(
        string $cupsServerKey,
        string $queueName,
        int $cupsJobId,
        PrintJobState $state,
        ?string $safeReasonCode,
        string $observedAt,
    ): bool {
        if ($cupsJobId < 1 || !in_array($state, [
            PrintJobState::Pending,
            PrintJobState::Processing,
            PrintJobState::Completed,
            PrintJobState::Cancelled,
            PrintJobState::Indeterminate,
        ], true)) {
            throw new InvalidArgumentException('The reconciled CUPS job state is invalid.');
        }

        $finishedAt = in_array($state, [PrintJobState::Completed, PrintJobState::Cancelled], true)
            ? $observedAt
            : null;
        $this->connection->beginTransaction();

        try {
            $statement = $this->connection->prepare(
                <<<'SQL'
                    UPDATE print_jobs
                    SET state = :state,
                        safe_error_code = COALESCE(:safe_reason_code, safe_error_code),
                        updated_at = :observed_at,
                        last_reconciled_at = :observed_at,
                        finished_at = COALESCE(:finished_at, finished_at)
                    WHERE cups_server_key = :cups_server_key
                      AND queue_name = :queue_name
                      AND cups_job_id = :cups_job_id
                      AND state NOT IN ('completed', 'cancelled', 'failed')
                    SQL,
            );
            $statement->execute([
                'state' => $state->value,
                'safe_reason_code' => $safeReasonCode,
                'observed_at' => $observedAt,
                'finished_at' => $finishedAt,
                'cups_server_key' => $cupsServerKey,
                'queue_name' => $queueName,
                'cups_job_id' => $cupsJobId,
            ]);

            if (1 !== $statement->rowCount()) {
                $this->connection->commit();

                return false;
            }

            $idStatement = $this->connection->prepare(
                'SELECT id FROM print_jobs WHERE cups_server_key = :cups_server_key '
                . 'AND queue_name = :queue_name AND cups_job_id = :cups_job_id',
            );
            $idStatement->execute([
                'cups_server_key' => $cupsServerKey,
                'queue_name' => $queueName,
                'cups_job_id' => $cupsJobId,
            ]);
            $printJobId = $idStatement->fetchColumn();

            if (false === $printJobId) {
                throw new RuntimeException('The reconciled print job could not be read.');
            }

            $this->appendEvent((string) $printJobId, $state, $safeReasonCode, $observedAt, 'cups');
            $this->connection->commit();

            return true;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    public function deleteExpired(string $cutoff, int $limit = 250): int
    {
        if ($limit < 1 || $limit > 1_000) {
            throw new InvalidArgumentException('The history cleanup limit is invalid.');
        }

        $statement = $this->connection->prepare(
            'DELETE FROM print_jobs WHERE id IN ('
            . 'SELECT id FROM print_jobs WHERE retained_until <= :cutoff ORDER BY retained_until ASC LIMIT :limit'
            . ')',
        );
        $statement->bindValue('cutoff', $cutoff);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount();
    }

    private function findBySubmissionKey(string $submissionKey): ?PrintJobRecord
    {
        $statement = $this->connection->prepare(
            'SELECT j.* FROM print_submission_keys k '
            . 'INNER JOIN print_jobs j ON j.id = k.print_job_id '
            . 'WHERE k.submission_key = :submission_key',
        );
        $statement->execute(['submission_key' => $submissionKey]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return false === $row ? null : $this->hydrate($row);
    }

    private function findById(string $printJobId): PrintJobRecord
    {
        $statement = $this->connection->prepare('SELECT * FROM print_jobs WHERE id = :id');
        $statement->execute(['id' => $printJobId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (false === $row) {
            throw new RuntimeException('The print job record does not exist.');
        }

        return $this->hydrate($row);
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): PrintJobRecord
    {
        $state = PrintJobState::tryFrom((string) $row['state']);

        if (null === $state) {
            throw new RuntimeException('The print job contains an unsupported state.');
        }

        return new PrintJobRecord(
            id: (string) $row['id'],
            correlationId: (string) $row['correlation_id'],
            cupsServerKey: (string) $row['cups_server_key'],
            queueName: (string) $row['queue_name'],
            cupsJobId: null === $row['cups_job_id'] ? null : (int) $row['cups_job_id'],
            state: $state,
            safeErrorCode: null === $row['safe_error_code'] ? null : (string) $row['safe_error_code'],
            safeErrorDetail: null === $row['safe_error_detail'] ? null : (string) $row['safe_error_detail'],
            submittedAt: (string) $row['submitted_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }

    private function appendEvent(
        string $printJobId,
        PrintJobState $state,
        ?string $reasonCode,
        string $observedAt,
        string $source = 'application',
    ): void {
        $statement = $this->connection->prepare(
            'INSERT INTO job_events (id, print_job_id, state, safe_reason_code, source, observed_at) '
            . 'VALUES (:id, :print_job_id, :state, :safe_reason_code, :source, :observed_at)',
        );
        $statement->execute([
            'id' => bin2hex(random_bytes(16)),
            'print_job_id' => $printJobId,
            'state' => $state->value,
            'safe_reason_code' => $reasonCode,
            'source' => $source,
            'observed_at' => $observedAt,
        ]);
    }
}
