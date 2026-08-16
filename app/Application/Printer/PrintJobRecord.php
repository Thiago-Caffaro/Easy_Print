<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

final readonly class PrintJobRecord
{
    public function __construct(
        public string $id,
        public string $correlationId,
        public string $cupsServerKey,
        public string $queueName,
        public ?int $cupsJobId,
        public PrintJobState $state,
        public ?string $safeErrorCode,
        public ?string $safeErrorDetail,
        public string $submittedAt,
        public string $updatedAt,
    ) {}
}
