<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

final readonly class PrintRequestInput
{
    /**
     * @param array<string,mixed> $options
     */
    public function __construct(
        public string $queueIdentifier,
        public string $capabilityFingerprint,
        public string $copies,
        public ?string $pageRange,
        public array $options,
    ) {}
}
