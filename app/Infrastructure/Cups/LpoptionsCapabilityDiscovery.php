<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Cups;

use function count;

use EasyPrint\Application\Printer\QueueCapabilityDiscovery;
use EasyPrint\Domain\Printer\CapabilitySnapshot;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Infrastructure\Process\ProcessResult;
use EasyPrint\Infrastructure\Process\ProcessRunner;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

use function str_contains;
use function strlen;

final readonly class LpoptionsCapabilityDiscovery implements QueueCapabilityDiscovery
{
    public function __construct(
        private ProcessRunner $processRunner,
        private LpoptionsOutputParser $parser,
        private string $host,
        private int $port,
        private bool $requireEncryption,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function discover(string $queueIdentifier): CapabilitySnapshot
    {
        $this->validateQueueIdentifier($queueIdentifier);
        $result = $this->run($queueIdentifier);

        if (!$result->succeeded()) {
            return $this->completed(CapabilitySnapshot::failed(
                $queueIdentifier,
                CupsFailureClassifier::classify($result),
            ));
        }

        try {
            $options = $this->parser->parse($result->stdout);

            return $this->completed(new CapabilitySnapshot(
                queueIdentifier: $queueIdentifier,
                connectivity: CupsConnectivity::Available,
                options: $options,
                fingerprint: $this->parser->fingerprint($options),
            ));
        } catch (MalformedLpoptionsOutput) {
            return $this->completed(CapabilitySnapshot::failed(
                $queueIdentifier,
                CupsConnectivity::MalformedResponse,
            ));
        }
    }

    private function run(string $queueIdentifier): ProcessResult
    {
        $arguments = ['-h', $this->serverAddress(), '-p', $queueIdentifier, '-l'];

        if ($this->requireEncryption) {
            $arguments = ['-E', ...$arguments];
        }

        return $this->processRunner->run('lpoptions', $arguments);
    }

    private function serverAddress(): string
    {
        $host = str_contains($this->host, ':') ? '[' . $this->host . ']' : $this->host;

        return $host . ':' . $this->port;
    }

    private function validateQueueIdentifier(string $queueIdentifier): void
    {
        if ('' === $queueIdentifier
            || strlen($queueIdentifier) > 127
            || 1 === preg_match('/[\x00-\x20\x7F\/#]/', $queueIdentifier)) {
            throw new InvalidArgumentException('The selected queue identifier is invalid for CUPS.');
        }
    }

    private function completed(CapabilitySnapshot $snapshot): CapabilitySnapshot
    {
        $this->logger->log(
            CupsConnectivity::Available === $snapshot->connectivity ? LogLevel::INFO : LogLevel::WARNING,
            'cups.capability_discovery.completed',
            [
                'connectivity' => $snapshot->connectivity->value,
                'option_count' => count($snapshot->options),
                'unknown_option_count' => count($snapshot->unknownOptions()),
            ],
        );

        return $snapshot;
    }
}
