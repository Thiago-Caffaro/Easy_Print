<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Health;

use function dirname;

use EasyPrint\Application\Health\ReadinessProbe;
use EasyPrint\Application\Health\ReadinessReport;
use EasyPrint\Application\Printer\QueueDiscovery;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Infrastructure\Persistence\SqliteConnectionFactory;

use function is_dir;
use function is_file;
use function is_writable;

use Psr\Log\LoggerInterface;
use Throwable;

final readonly class OperationalReadinessProbe implements ReadinessProbe
{
    public function __construct(
        private string $databasePath,
        private string $temporaryPath,
        private QueueDiscovery $queueDiscovery,
        private LoggerInterface $logger,
    ) {}

    public function check(): ReadinessReport
    {
        $storageReady = $this->storageReady();
        $databaseReady = $this->databaseReady();
        $cupsConnectivity = $this->cupsConnectivity();

        return new ReadinessReport($storageReady, $databaseReady, $cupsConnectivity);
    }

    private function storageReady(): bool
    {
        $ready = is_dir($this->temporaryPath)
            && is_writable($this->temporaryPath)
            && is_dir(dirname($this->databasePath))
            && is_writable(dirname($this->databasePath));

        if (!$ready) {
            $this->logger->warning('health.storage.unavailable', ['component' => 'storage']);
        }

        return $ready;
    }

    private function databaseReady(): bool
    {
        if (!is_file($this->databasePath)) {
            $this->logger->error('health.database.unavailable', ['component' => 'database']);

            return false;
        }

        try {
            $connection = SqliteConnectionFactory::create($this->databasePath);
            $statement = $connection->query('SELECT COUNT(*) FROM schema_migrations');

            if (false === $statement) {
                return false;
            }

            $statement->fetchColumn();
            $statement->closeCursor();
            $connection = null;

            return true;
        } catch (Throwable $exception) {
            $this->logger->error('health.database.unavailable', [
                'component' => 'database',
                'exception' => $exception,
            ]);

            return false;
        }
    }

    private function cupsConnectivity(): CupsConnectivity
    {
        try {
            return $this->queueDiscovery->discover()->connectivity;
        } catch (Throwable $exception) {
            $this->logger->warning('health.cups.unavailable', [
                'component' => 'cups',
                'exception' => $exception,
            ]);

            return CupsConnectivity::Unavailable;
        }
    }
}
