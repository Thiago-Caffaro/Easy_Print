<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Cups;

use EasyPrint\Application\Printer\QueueDiscovery;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Domain\Printer\QueueSnapshot;
use EasyPrint\Infrastructure\Process\ProcessFailureReason;
use EasyPrint\Infrastructure\Process\ProcessResult;
use EasyPrint\Infrastructure\Process\ProcessRunner;

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
    ) {}

    public function discover(): QueueSnapshot
    {
        $scheduler = $this->run(['-r']);

        if (!$scheduler->succeeded()) {
            return QueueSnapshot::failed($this->connectivityForFailure($scheduler));
        }

        try {
            if (!$this->parser->schedulerIsRunning($scheduler->stdout)) {
                return QueueSnapshot::failed(CupsConnectivity::Unavailable);
            }

            $default = $this->run(['-d']);

            if (!$default->succeeded()) {
                return QueueSnapshot::failed($this->connectivityForFailure($default));
            }

            $queues = $this->run(['-e']);

            if (!$queues->succeeded()) {
                return QueueSnapshot::failed($this->connectivityForFailure($queues));
            }

            return new QueueSnapshot(
                connectivity: CupsConnectivity::Available,
                queueIdentifiers: $this->parser->queueIdentifiers($queues->stdout),
                defaultQueueIdentifier: $this->parser->defaultQueueIdentifier($default->stdout),
            );
        } catch (MalformedLpstatOutput) {
            return QueueSnapshot::failed(CupsConnectivity::MalformedResponse);
        }
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

    private function connectivityForFailure(ProcessResult $result): CupsConnectivity
    {
        if (ProcessFailureReason::TimedOut === $result->failureReason) {
            return CupsConnectivity::TimedOut;
        }

        if (ProcessFailureReason::OutputLimit === $result->failureReason) {
            return CupsConnectivity::MalformedResponse;
        }

        $diagnostic = strtolower($result->stderr . "\n" . $result->stdout);

        foreach (['not authorized', 'unauthorized', 'forbidden', 'client-error-not-authorized'] as $marker) {
            if (str_contains($diagnostic, $marker)) {
                return CupsConnectivity::Unauthorized;
            }
        }

        return CupsConnectivity::Unavailable;
    }
}
