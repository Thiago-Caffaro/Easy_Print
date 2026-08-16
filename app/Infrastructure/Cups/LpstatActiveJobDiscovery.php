<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Cups;

use function array_key_exists;
use function array_unique;
use function array_values;
use function count;

use EasyPrint\Application\Printer\ActiveJobDiscovery;
use EasyPrint\Domain\Printer\ActiveJobSnapshot;
use EasyPrint\Domain\Printer\ActiveJobState;
use EasyPrint\Domain\Printer\ActivePrintJob;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Domain\Printer\PrinterState;
use EasyPrint\Infrastructure\Process\ProcessResult;
use EasyPrint\Infrastructure\Process\ProcessRunner;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

use function str_contains;

final readonly class LpstatActiveJobDiscovery implements ActiveJobDiscovery
{
    public function __construct(
        private ProcessRunner $processRunner,
        private LpstatJobOutputParser $jobParser,
        private LpstatOutputParser $printerParser,
        private string $host,
        private int $port,
        private bool $requireEncryption,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function discover(): ActiveJobSnapshot
    {
        $snapshot = $this->discoverSnapshot();
        $this->logger->log(
            CupsConnectivity::Available === $snapshot->connectivity ? LogLevel::INFO : LogLevel::WARNING,
            'cups.active_job_discovery.completed',
            [
                'connectivity' => $snapshot->connectivity->value,
                'job_count' => count($snapshot->jobs),
            ],
        );

        return $snapshot;
    }

    private function discoverSnapshot(): ActiveJobSnapshot
    {
        $result = $this->run(['-W', 'not-completed', '-o']);

        if (!$result->succeeded()) {
            return ActiveJobSnapshot::failed(CupsFailureClassifier::classify($result));
        }

        try {
            $jobs = $this->jobParser->activeJobs($result->stdout);
        } catch (MalformedLpstatOutput) {
            return ActiveJobSnapshot::failed(CupsConnectivity::MalformedResponse);
        }

        if ([] === $jobs) {
            return new ActiveJobSnapshot(CupsConnectivity::Available);
        }

        $queueIdentifiers = array_values(array_unique(array_map(
            static fn(ActivePrintJob $job): string => $job->queueIdentifier,
            $jobs,
        )));
        $printerResult = $this->run(['-p']);

        if (!$printerResult->succeeded()) {
            return new ActiveJobSnapshot(CupsConnectivity::Available, $jobs);
        }

        $queueStates = $this->printerParser->queueStates($printerResult->stdout, $queueIdentifiers);
        $processingJobIds = $this->printerParser->processingJobIds($printerResult->stdout, $queueIdentifiers);
        $normalized = array_map(
            static function (ActivePrintJob $job) use ($queueStates, $processingJobIds): ActivePrintJob {
                $queueState = $queueStates[$job->queueIdentifier] ?? PrinterState::Unknown;
                $state = match (true) {
                    PrinterState::Processing === $queueState
                        && array_key_exists($job->queueIdentifier, $processingJobIds)
                        && $job->cupsJobId === $processingJobIds[$job->queueIdentifier] => ActiveJobState::Processing,
                    PrinterState::Ready === $queueState,
                    PrinterState::Stopped === $queueState,
                    PrinterState::Processing === $queueState
                        && array_key_exists($job->queueIdentifier, $processingJobIds) => ActiveJobState::Pending,
                    default => ActiveJobState::Unknown,
                };

                return new ActivePrintJob(
                    $job->queueIdentifier,
                    $job->cupsJobId,
                    $job->byteSize,
                    $job->submittedAtLabel,
                    $state,
                );
            },
            $jobs,
        );

        return new ActiveJobSnapshot(CupsConnectivity::Available, $normalized);
    }

    /**
     * @param list<string> $operationArguments
     */
    private function run(array $operationArguments): ProcessResult
    {
        $arguments = ['-h', $this->serverAddress(), ...$operationArguments];

        if ($this->requireEncryption) {
            $arguments = ['-E', ...$arguments];
        }

        return $this->processRunner->run('lpstat', $arguments);
    }

    private function serverAddress(): string
    {
        $host = str_contains($this->host, ':') ? '[' . $this->host . ']' : $this->host;

        return $host . ':' . $this->port;
    }
}
