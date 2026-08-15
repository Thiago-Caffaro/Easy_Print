<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

use EasyPrint\Domain\Printer\CapabilitySnapshot;

interface CapabilitySnapshotCache
{
    public function find(string $serverKey, string $queueIdentifier, int $now): ?CapabilitySnapshot;

    public function save(string $serverKey, CapabilitySnapshot $snapshot, int $cachedAt, int $expiresAt): void;

    public function invalidate(string $serverKey, string $queueIdentifier): void;
}
