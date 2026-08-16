<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

final readonly class PrintSubmissionResult
{
    public function __construct(
        public PrintJobRecord $record,
        public bool $duplicate,
        public bool $temporaryFileDeleted,
    ) {}
}
