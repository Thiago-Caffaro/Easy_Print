<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

use EasyPrint\Domain\Printer\CapabilitySnapshot;

interface QueueCapabilityDiscovery
{
    public function discover(string $queueIdentifier): CapabilitySnapshot;
}
