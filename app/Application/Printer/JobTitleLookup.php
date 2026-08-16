<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

interface JobTitleLookup
{
    public function findOriginalName(string $cupsServerKey, string $queueIdentifier, int $cupsJobId): ?string;
}
