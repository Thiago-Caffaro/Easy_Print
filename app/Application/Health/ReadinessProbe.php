<?php

declare(strict_types=1);

namespace EasyPrint\Application\Health;

interface ReadinessProbe
{
    public function check(): ReadinessReport;
}
