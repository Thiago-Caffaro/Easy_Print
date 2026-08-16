<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Support;

use EasyPrint\Application\Printer\QueueStatusDiscovery;
use EasyPrint\Domain\Printer\PrinterStatusSnapshot;

final readonly class FakeQueueStatusDiscovery implements QueueStatusDiscovery
{
    public function __construct(private PrinterStatusSnapshot $snapshot) {}

    public function discover(string $queueIdentifier): PrinterStatusSnapshot
    {
        return $this->snapshot;
    }
}
