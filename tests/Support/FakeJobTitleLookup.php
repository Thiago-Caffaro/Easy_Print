<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Support;

use EasyPrint\Application\Printer\JobTitleLookup;

final readonly class FakeJobTitleLookup implements JobTitleLookup
{
    /**
     * @param array<string,string> $titlesByRequestIdentifier
     */
    public function __construct(private array $titlesByRequestIdentifier = []) {}

    public function findOriginalName(string $cupsServerKey, string $queueIdentifier, int $cupsJobId): ?string
    {
        return $this->titlesByRequestIdentifier[$queueIdentifier . '-' . $cupsJobId] ?? null;
    }
}
