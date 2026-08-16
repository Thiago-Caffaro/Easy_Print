<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

final readonly class PrintHistoryEntry
{
    /**
     * @param array<string,string> $selectedOptions
     */
    public function __construct(
        public string $id,
        public string $queueName,
        public ?int $cupsJobId,
        public ?string $originalName,
        public string $mediaType,
        public int $byteSize,
        public int $copies,
        public ?string $pageRange,
        public array $selectedOptions,
        public PrintJobState $state,
        public ?string $safeErrorCode,
        public string $submittedAt,
        public string $updatedAt,
        public ?string $finishedAt,
    ) {}
}
