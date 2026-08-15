<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Support;

use EasyPrint\Application\Health\ReadinessProbe;
use EasyPrint\Application\Health\ReadinessReport;

final class FakeReadinessProbe implements ReadinessProbe
{
    public int $calls = 0;

    public function __construct(private readonly ReadinessReport $report) {}

    public function check(): ReadinessReport
    {
        ++$this->calls;

        return $this->report;
    }
}
