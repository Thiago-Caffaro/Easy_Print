<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Cups;

use function count;

use EasyPrint\Application\Printer\QueueDiscovery;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Domain\Printer\PrinterQueue;
use EasyPrint\Domain\Printer\PrinterState;
use EasyPrint\Domain\Printer\QueueSnapshot;
use EasyPrint\Infrastructure\Process\ProcessFailureReason;
use EasyPrint\Infrastructure\Process\ProcessResult;
use EasyPrint\Infrastructure\Process\ProcessRunner;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

use function str_contains;
use function strtolower;

final readonly class LpstatQueueDiscovery implements QueueDiscovery
{
    public function __construct(
        private ProcessRunner $processRunner,
        private LpstatOutputParser $parser,
        private string $host,
        private int $port,
        private bool $requireEncryption,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function discover(): QueueSnapshot
    {
        $snapshot = $this->discoverSnapshot();
        $this->logger->log(
            CupsConnectivity::Available === $snapshot->connectivity ? LogLevel::INFO : LogLevel::WARNING,
            'cups.queue_discovery.completed',
            [
                'connectivity' => $snapshot->connectivity->value,
                'queue_count' => count($snapshot->queues),
            ],
        );

        return $snapshot;
    }

    private function discoverSnapshot(): QueueSnapshot
    {
        $scheduler = $this->run(['-r']);

        if (!$scheduler->succeeded()) {
            return QueueSnapshot::failed(CupsFailureClassifier::classify($scheduler));
        }

        try {
            if (!$this->parser->schedulerIsRunning($scheduler->stdout)) {
                return QueueSnapshot::failed(CupsConnectivity::Unavailable);
            }

            $default = $this->run(['-d']);

            if (!$default->succeeded()) {
                return QueueSnapshot::failed(CupsFailureClassifier::classify($default));
            }

            $queues = $this->run(['-e']);

            if (!$queues->succeeded()) {
                return QueueSnapshot::failed(CupsFailureClassifier::classify($queues));
            }

            $identifiers = $this->parser->queueIdentifiers($queues->stdout);
            $states = $this->queueStates($identifiers);

            return new QueueSnapshot(
                connectivity: CupsConnectivity::Available,
                queues: array_map(
                    static fn(string $identifier): PrinterQueue => new PrinterQueue($identifier, $states[$identifier]),
                    $identifiers,
                ),
                defaultQueueIdentifier: $this->parser->defaultQueueIdentifier($default->stdout),
            );
        } catch (MalformedLpstatOutput) {
            return QueueSnapshot::failed(CupsConnectivity::MalformedResponse);
        }
    }

    /**
     * @param list<string> $identifiers
     *
     * @return array<string,PrinterState>
     */
    private function queueStates(array $identifiers): array
    {
        if ([] === $identifiers) {
            return [];
        }

        $states = $this->run(['-p']);

        if (!$states->succeeded()) {
            $fallback = ProcessFailureReason::OutputLimit === $states->failureReason
                ? PrinterState::Unknown
                : PrinterState::Unavailable;

            return array_fill_keys($identifiers, $fallback);
        }

        return $this->parser->queueStates($states->stdout, $identifiers);
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
