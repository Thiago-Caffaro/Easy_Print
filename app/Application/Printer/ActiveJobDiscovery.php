<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

use EasyPrint\Domain\Printer\ActiveJobSnapshot;

interface ActiveJobDiscovery
{
    public function discover(): ActiveJobSnapshot;
}
