<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

use InvalidArgumentException;

final readonly class CupsJobSubmission
{
    private function __construct(
        public PrintJobState $state,
        public ?int $cupsJobId,
        public ?string $safeErrorCode,
        public ?string $safeDiagnostic,
    ) {
        if (PrintJobState::Accepted === $state && (null === $cupsJobId || $cupsJobId < 1 || null !== $safeErrorCode)) {
            throw new InvalidArgumentException('An accepted submission requires a CUPS job identifier and no error.');
        }

        if (PrintJobState::Accepted !== $state && (null !== $cupsJobId || null === $safeErrorCode)) {
            throw new InvalidArgumentException('An unsuccessful submission requires a safe error and no CUPS job identifier.');
        }
    }

    public static function accepted(int $cupsJobId): self
    {
        return new self(PrintJobState::Accepted, $cupsJobId, null, null);
    }

    public static function failed(string $safeErrorCode, ?string $safeDiagnostic = null): self
    {
        return new self(PrintJobState::Failed, null, $safeErrorCode, $safeDiagnostic);
    }

    public static function indeterminate(string $safeErrorCode, ?string $safeDiagnostic = null): self
    {
        return new self(PrintJobState::Indeterminate, null, $safeErrorCode, $safeDiagnostic);
    }
}
