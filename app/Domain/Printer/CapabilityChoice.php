<?php

declare(strict_types=1);

namespace EasyPrint\Domain\Printer;

use InvalidArgumentException;

use function preg_match;

final readonly class CapabilityChoice
{
    public function __construct(public string $technicalIdentifier)
    {
        if (1 !== preg_match('/^-?[A-Za-z0-9][A-Za-z0-9_.:+\/-]{0,127}$/D', $technicalIdentifier)) {
            throw new InvalidArgumentException('A capability choice identifier is invalid.');
        }
    }
}
