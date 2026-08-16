<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

final readonly class ValidatedPrintArguments
{
    /**
     * @param array<string,string> $selectedOptions
     * @param list<string>         $arguments
     */
    public function __construct(
        public string $queueIdentifier,
        public int $copies,
        public ?string $pageRange,
        public array $selectedOptions,
        public array $arguments,
    ) {}
}
