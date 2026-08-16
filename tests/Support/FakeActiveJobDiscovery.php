<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Support;

use EasyPrint\Application\Printer\ActiveJobDiscovery;
use EasyPrint\Domain\Printer\ActiveJobSnapshot;

final readonly class FakeActiveJobDiscovery implements ActiveJobDiscovery
{
    public function __construct(private ActiveJobSnapshot $snapshot) {}

    public function discover(): ActiveJobSnapshot
    {
        return $this->snapshot;
    }
}
