<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

final readonly class PrintSubmissionInput
{
    public function __construct(
        public string $submissionKey,
        public string $correlationId,
        public string $cupsServerKey,
        public PrintDocument $document,
        public ValidatedPrintArguments $arguments,
    ) {}
}
