<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

use EasyPrint\Domain\Printer\PrinterStatusSnapshot;

interface QueueStatusDiscovery
{
    public function discover(string $queueIdentifier): PrinterStatusSnapshot;
}
