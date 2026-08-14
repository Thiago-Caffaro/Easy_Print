<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Process;

final readonly class ProcessResult
{
    public function __construct(
        public string $executableKey,
        public string $stdout,
        public string $stderr,
        public ?int $exitCode,
        public int $durationMilliseconds,
        public ?ProcessFailureReason $failureReason,
    ) {}

    public function succeeded(): bool
    {
        return null === $this->failureReason && 0 === $this->exitCode;
    }

    public function timedOut(): bool
    {
        return ProcessFailureReason::TimedOut === $this->failureReason;
    }

    public function outputWasTruncated(): bool
    {
        return ProcessFailureReason::OutputLimit === $this->failureReason;
    }
}
