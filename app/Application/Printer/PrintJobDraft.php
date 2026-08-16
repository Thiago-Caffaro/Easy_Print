<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

final readonly class PrintJobDraft
{
    public function __construct(
        public string $id,
        public string $correlationId,
        public string $cupsServerKey,
        public string $queueName,
        public string $originalName,
        public string $mediaType,
        public int $byteSize,
        public int $copies,
        public ?string $pageRange,
        /** @var array<string,string> */
        public array $selectedOptions,
        public string $submittedAt,
        public string $retainedUntil,
    ) {}
}
