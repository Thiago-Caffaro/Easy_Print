<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Cups;

use function count;

use EasyPrint\Application\Printer\QueueStatusDiscovery;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Domain\Printer\PrinterStatusSnapshot;
use EasyPrint\Infrastructure\Process\ProcessResult;
use EasyPrint\Infrastructure\Process\ProcessRunner;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

use function str_contains;

final readonly class LpstatQueueStatusDiscovery implements QueueStatusDiscovery
{
    public function __construct(
        private ProcessRunner $processRunner,
        private LpstatPrinterStatusParser $parser,
        private string $host,
        private int $port,
        private bool $requireEncryption,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function discover(string $queueIdentifier): PrinterStatusSnapshot
    {
        $status = $this->run(['-l', '-p', $queueIdentifier]);

        if (!$status->succeeded()) {
            return $this->failed($queueIdentifier, CupsFailureClassifier::classify($status));
        }

        $accepting = $this->run(['-a', $queueIdentifier]);

        if (!$accepting->succeeded()) {
            return $this->failed($queueIdentifier, CupsFailureClassifier::classify($accepting));
        }

        try {
            [$state, $reasons] = $this->parser->status($status->stdout, $queueIdentifier);
            $snapshot = new PrinterStatusSnapshot(
                CupsConnectivity::Available,
                $queueIdentifier,
                $state,
                $this->parser->accepting($accepting->stdout, $queueIdentifier),
                $reasons,
            );
            $this->logger->log(LogLevel::INFO, 'cups.printer_status.completed', [
                'connectivity' => $snapshot->connectivity->value,
                'reason_count' => count($snapshot->reasons),
            ]);

            return $snapshot;
        } catch (MalformedLpstatOutput) {
            return $this->failed($queueIdentifier, CupsConnectivity::MalformedResponse);
        }
    }

    private function failed(string $queueIdentifier, CupsConnectivity $connectivity): PrinterStatusSnapshot
    {
        $this->logger->log(LogLevel::WARNING, 'cups.printer_status.completed', [
            'connectivity' => $connectivity->value,
            'reason_count' => 0,
        ]);

        return PrinterStatusSnapshot::failed($queueIdentifier, $connectivity);
    }

    /** @param list<string> $operationArguments */
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
