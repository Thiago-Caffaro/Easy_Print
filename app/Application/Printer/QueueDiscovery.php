<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

use EasyPrint\Domain\Printer\QueueSnapshot;

interface QueueDiscovery
{
    public function discover(): QueueSnapshot;
}
