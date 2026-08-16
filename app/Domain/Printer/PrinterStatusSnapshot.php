<?php

declare(strict_types=1);

namespace EasyPrint\Domain\Printer;

final readonly class PrinterStatusSnapshot
{
    /** @param list<string> $reasons */
    public function __construct(
        public CupsConnectivity $connectivity,
        public string $queueIdentifier,
        public PrinterState $state,
        public ?bool $acceptingJobs,
        public array $reasons,
    ) {}

    public static function failed(string $queueIdentifier, CupsConnectivity $connectivity): self
    {
        return new self($connectivity, $queueIdentifier, PrinterState::Unavailable, null, []);
    }
}
