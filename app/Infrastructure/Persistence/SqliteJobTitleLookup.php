<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Persistence;

use EasyPrint\Application\Printer\JobTitleLookup;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class SqliteJobTitleLookup implements JobTitleLookup
{
    private ?PDO $connection = null;

    public function __construct(
        private readonly string $databasePath,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function findOriginalName(string $cupsServerKey, string $queueIdentifier, int $cupsJobId): ?string
    {
        try {
            $statement = $this->connection()->prepare(
                'SELECT original_name FROM print_jobs '
                . 'WHERE cups_server_key = :cups_server_key AND queue_name = :queue_name AND cups_job_id = :cups_job_id '
                . 'ORDER BY submitted_at DESC LIMIT 1',
            );
            $statement->execute([
                'cups_server_key' => $cupsServerKey,
                'queue_name' => $queueIdentifier,
                'cups_job_id' => $cupsJobId,
            ]);
            $name = $statement->fetchColumn();

            return false === $name || null === $name ? null : (string) $name;
        } catch (PDOException $exception) {
            $this->logger->warning('database.job_title_lookup.failed', [
                'component' => 'database',
                'exception' => $exception,
            ]);

            return null;
        }
    }

    private function connection(): PDO
    {
        return $this->connection ??= SqliteConnectionFactory::create($this->databasePath);
    }
}
