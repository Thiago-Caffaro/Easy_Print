<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

use LogicException;

final readonly class PrintArgumentResult
{
    private function __construct(
        public ?ValidatedPrintArguments $validated,
        public ?PrintRequestFailure $failure,
    ) {
        if ((null === $validated) === (null === $failure)) {
            throw new LogicException('A print argument result must contain either validated arguments or one failure.');
        }
    }

    public static function accepted(ValidatedPrintArguments $validated): self
    {
        return new self($validated, null);
    }

    public static function rejected(PrintRequestFailure $failure): self
    {
        return new self(null, $failure);
    }

    public function succeeded(): bool
    {
        return null !== $this->validated;
    }
}
