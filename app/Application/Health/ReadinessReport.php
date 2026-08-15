<?php

declare(strict_types=1);

namespace EasyPrint\Application\Health;

use EasyPrint\Domain\Printer\CupsConnectivity;

final readonly class ReadinessReport
{
    public function __construct(
        public bool $storageReady,
        public bool $databaseReady,
        public CupsConnectivity $cupsConnectivity,
    ) {}

    public function status(): HealthStatus
    {
        if (!$this->storageReady || !$this->databaseReady) {
            return HealthStatus::Unavailable;
        }

        return CupsConnectivity::Available === $this->cupsConnectivity
            ? HealthStatus::Ok
            : HealthStatus::Degraded;
    }

    /**
     * @return array{application:string,storage:string,database:string,cups:string}
     */
    public function checks(): array
    {
        return [
            'application' => HealthStatus::Ok->value,
            'storage' => $this->storageReady ? HealthStatus::Ok->value : HealthStatus::Unavailable->value,
            'database' => $this->databaseReady ? HealthStatus::Ok->value : HealthStatus::Unavailable->value,
            'cups' => $this->cupsConnectivity->value,
        ];
    }
}
