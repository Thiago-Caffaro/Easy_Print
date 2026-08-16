<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Persistence;

use function array_filter;
use function array_map;

use EasyPrint\Application\Printer\PrintHistoryEntry;
use EasyPrint\Application\Printer\PrintHistoryPage;
use EasyPrint\Application\Printer\PrintHistoryReader;
use EasyPrint\Application\Printer\PrintJobState;

use function is_array;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

use function min;

use PDO;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final readonly class SqlitePrintHistoryReader implements PrintHistoryReader
{
    public function __construct(
        private string $databasePath,
        private LoggerInterface $logger,
    ) {}

    public function readPage(int $page, int $perPage): PrintHistoryPage
    {
        $page = min(10_000, max(1, $page));
        $perPage = min(50, max(1, $perPage));

        try {
            $connection = SqliteConnectionFactory::create($this->databasePath);
            $countStatement = $connection->query('SELECT COUNT(*) FROM print_jobs');

            if (false === $countStatement) {
                throw new RuntimeException('The print history count could not be read.');
            }

            $total = (int) $countStatement->fetchColumn();
            $maximumPage = max(1, (int) ceil($total / $perPage));
            $page = min($page, $maximumPage);
            $statement = $connection->prepare(
                'SELECT id, queue_name, cups_job_id, original_name, detected_media_type, byte_size, '
                . 'copies, page_range, options_json, state, safe_error_code, submitted_at, updated_at, finished_at '
                . 'FROM print_jobs ORDER BY submitted_at DESC, id DESC LIMIT :limit OFFSET :offset',
            );
            $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
            $statement->bindValue('offset', ($page - 1) * $perPage, PDO::PARAM_INT);
            $statement->execute();
            $entries = [];

            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $state = PrintJobState::tryFrom((string) $row['state']);

                if (null === $state) {
                    continue;
                }

                $entries[] = new PrintHistoryEntry(
                    id: (string) $row['id'],
                    queueName: (string) $row['queue_name'],
                    cupsJobId: null === $row['cups_job_id'] ? null : (int) $row['cups_job_id'],
                    originalName: null === $row['original_name'] ? null : (string) $row['original_name'],
                    mediaType: (string) $row['detected_media_type'],
                    byteSize: (int) $row['byte_size'],
                    copies: (int) $row['copies'],
                    pageRange: null === $row['page_range'] ? null : (string) $row['page_range'],
                    selectedOptions: $this->decodeOptions((string) $row['options_json']),
                    state: $state,
                    safeErrorCode: null === $row['safe_error_code'] ? null : (string) $row['safe_error_code'],
                    submittedAt: (string) $row['submitted_at'],
                    updatedAt: (string) $row['updated_at'],
                    finishedAt: null === $row['finished_at'] ? null : (string) $row['finished_at'],
                );
            }

            return new PrintHistoryPage($entries, $page, $perPage, $total);
        } catch (Throwable $exception) {
            $this->logger->warning('history.read.failed', ['component' => 'database']);

            return PrintHistoryPage::unavailable($page, $perPage);
        }
    }

    /** @return array<string,string> */
    private function decodeOptions(string $json): array
    {
        try {
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        $values = 1 === ($decoded['version'] ?? null) && is_array($decoded['values'] ?? null)
            ? $decoded['values']
            : $decoded;

        return array_filter(
            array_map(static fn(mixed $value): string => is_string($value) ? $value : '', $values),
            static fn(string $value, mixed $key): bool => '' !== $value && is_string($key),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
