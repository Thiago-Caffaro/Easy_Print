<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

interface PrintHistoryMaintenance
{
    public function reconcile(
        string $cupsServerKey,
        string $queueName,
        int $cupsJobId,
        PrintJobState $state,
        ?string $safeReasonCode,
        string $observedAt,
    ): bool;

    public function deleteExpired(string $cutoff, int $limit = 250): int;
}
