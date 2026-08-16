<?php

declare(strict_types=1);

namespace EasyPrint\Domain\Printer;

use InvalidArgumentException;

final readonly class PrinterQueue
{
    public function __construct(
        public string $identifier,
        public PrinterState $state,
    ) {
        if ('' === $identifier) {
            throw new InvalidArgumentException('A queue identifier cannot be empty.');
        }
    }
}
