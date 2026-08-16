<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Support;

use EasyPrint\Application\Printer\QueueDiscovery;
use EasyPrint\Domain\Printer\QueueSnapshot;

final readonly class FakeQueueDiscovery implements QueueDiscovery
{
    public function __construct(private QueueSnapshot $snapshot) {}

    public function discover(): QueueSnapshot
    {
        return $this->snapshot;
    }
}
