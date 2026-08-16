<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Cups;

use EasyPrint\Application\Printer\CupsJobSubmission;
use EasyPrint\Application\Printer\CupsJobSubmitter;
use EasyPrint\Application\Printer\PrintDocument;
use EasyPrint\Application\Printer\ValidatedPrintArguments;
use EasyPrint\Infrastructure\Process\ProcessFailureReason;
use EasyPrint\Infrastructure\Process\ProcessResult;
use EasyPrint\Infrastructure\Process\ProcessRunner;

use function sprintf;
use function str_contains;

final readonly class LpPrintJobSubmitter implements CupsJobSubmitter
{
    public function __construct(
        private ProcessRunner $processRunner,
        private LpSubmissionOutputParser $parser,
        private string $host,
        private int $port,
        private bool $requireEncryption,
    ) {}

    public function submit(ValidatedPrintArguments $arguments, PrintDocument $document): CupsJobSubmission
    {
        $commandArguments = [
            '-h',
            $this->serverAddress(),
            ...$arguments->arguments,
            '--',
            $document->absolutePath,
        ];

        if ($this->requireEncryption) {
            $commandArguments = ['-E', ...$commandArguments];
        }

        $result = $this->processRunner->run('lp', $commandArguments);

        if ($result->succeeded()) {
            $cupsJobId = $this->parser->cupsJobId($result->stdout, $arguments->queueIdentifier);

            return null === $cupsJobId
                ? CupsJobSubmission::indeterminate('cups_response_unrecognized', $this->diagnostic($result))
                : CupsJobSubmission::accepted($cupsJobId);
        }

        return match ($result->failureReason) {
            ProcessFailureReason::TimedOut => CupsJobSubmission::indeterminate(
                'cups_submission_timeout',
                $this->diagnostic($result),
            ),
            ProcessFailureReason::OutputLimit => CupsJobSubmission::indeterminate(
                'cups_response_too_large',
                $this->diagnostic($result),
            ),
            ProcessFailureReason::NonZeroExit => CupsJobSubmission::failed(
                'cups_rejected_submission',
                $this->diagnostic($result),
            ),
            ProcessFailureReason::StartFailed => CupsJobSubmission::failed(
                'cups_process_unavailable',
                $this->diagnostic($result),
            ),
            ProcessFailureReason::InvalidArgument,
            ProcessFailureReason::NotAllowed,
            null => CupsJobSubmission::failed(
                'submission_configuration_error',
                $this->diagnostic($result),
            ),
        };
    }

    private function serverAddress(): string
    {
        $host = str_contains($this->host, ':') ? '[' . $this->host . ']' : $this->host;

        return $host . ':' . $this->port;
    }

    private function diagnostic(ProcessResult $result): string
    {
        $failure = null === $result->failureReason ? 'none' : $result->failureReason->value;

        return sprintf(
            'failure=%s;exit=%s;duration_ms=%d',
            $failure,
            null === $result->exitCode ? 'unknown' : (string) $result->exitCode,
            $result->durationMilliseconds,
        );
    }
}
