<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

use EasyPrint\Domain\Printer\ActiveJobState;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Infrastructure\Configuration\AppConfig;
use EasyPrint\Infrastructure\Process\ProcessFailureReason;
use EasyPrint\Infrastructure\Process\ProcessRunner;

use function preg_match;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

use function sprintf;
use function str_contains;

final readonly class PrintJobCancellation
{
    public function __construct(
        private AppConfig $config,
        private ActiveJobDiscovery $jobs,
        private ProcessRunner $processRunner,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function cancel(string $queueIdentifier, int $cupsJobId): CancelJobResult
    {
        if (1 !== preg_match('/\A[A-Za-z0-9][A-Za-z0-9_.-]{0,126}\z/D', $queueIdentifier) || $cupsJobId < 1) {
            return new CancelJobResult(CancelJobStatus::NotFound);
        }

        $before = $this->jobs->discover();

        if (CupsConnectivity::Available !== $before->connectivity) {
            return new CancelJobResult(CancelJobStatus::Unavailable);
        }

        $job = null;

        foreach ($before->jobs as $candidate) {
            if ($candidate->queueIdentifier === $queueIdentifier && $candidate->cupsJobId === $cupsJobId) {
                $job = $candidate;
                break;
            }
        }

        if (null === $job) {
            return new CancelJobResult(CancelJobStatus::NotFound);
        }

        if (ActiveJobState::Pending !== $job->state && ActiveJobState::Processing !== $job->state) {
            return new CancelJobResult(CancelJobStatus::NotCancelable);
        }

        $arguments = ['-h', $this->serverAddress(), $queueIdentifier . '-' . $cupsJobId];

        if ('required' === $this->config->cupsEncryption) {
            $arguments = ['-E', ...$arguments];
        }

        $result = $this->processRunner->run('cancel', $arguments);

        if (!$result->succeeded()) {
            $status = ProcessFailureReason::TimedOut === $result->failureReason
                ? CancelJobStatus::Unavailable
                : CancelJobStatus::Failed;
            $this->logFailure($status, $queueIdentifier, $cupsJobId, $result->failureReason);

            return new CancelJobResult($status, $this->diagnostic($result->failureReason));
        }

        $after = $this->jobs->discover();

        if (CupsConnectivity::Available !== $after->connectivity) {
            return new CancelJobResult(CancelJobStatus::Unavailable);
        }

        foreach ($after->jobs as $candidate) {
            if ($candidate->queueIdentifier === $queueIdentifier && $candidate->cupsJobId === $cupsJobId) {
                $this->logFailure(CancelJobStatus::Failed, $queueIdentifier, $cupsJobId, null);

                return new CancelJobResult(CancelJobStatus::Failed, 'job_remains_active');
            }
        }

        return new CancelJobResult(CancelJobStatus::Cancelled);
    }

    private function serverAddress(): string
    {
        $host = str_contains($this->config->cupsHost, ':')
            ? '[' . $this->config->cupsHost . ']'
            : $this->config->cupsHost;

        return $host . ':' . $this->config->cupsPort;
    }

    private function diagnostic(?ProcessFailureReason $reason): string
    {
        return null === $reason ? 'unknown' : $reason->value;
    }

    private function logFailure(
        CancelJobStatus $status,
        string $queueIdentifier,
        int $cupsJobId,
        ?ProcessFailureReason $reason,
    ): void {
        $this->logger->log(
            CancelJobStatus::Failed === $status ? LogLevel::ERROR : LogLevel::WARNING,
            'cups.job_cancellation.failed',
            [
                'status' => $status->value,
                'queue' => $queueIdentifier,
                'job_id' => $cupsJobId,
                'reason' => null === $reason ? 'job_remains_active' : $reason->value,
            ],
        );
    }
}
