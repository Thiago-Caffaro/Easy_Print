<?php

declare(strict_types=1);

namespace EasyPrint\Domain\Printer;

use InvalidArgumentException;

final readonly class ActivePrintJob
{
    public function __construct(
        public string $queueIdentifier,
        public int $cupsJobId,
        public int $byteSize,
        public string $submittedAtLabel,
        public ActiveJobState $state,
    ) {
        if ('' === $queueIdentifier || $cupsJobId < 1 || $byteSize < 0 || '' === $submittedAtLabel) {
            throw new InvalidArgumentException('An active job requires valid queue, identifier, size, and timestamp data.');
        }
    }

    public function requestIdentifier(): string
    {
        return $this->queueIdentifier . '-' . $this->cupsJobId;
    }
}
