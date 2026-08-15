<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Integration\Infrastructure\Cups;

use EasyPrint\Domain\Printer\CapabilityCategory;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Infrastructure\Cups\LpoptionsCapabilityDiscovery;
use EasyPrint\Infrastructure\Cups\LpoptionsOutputParser;
use EasyPrint\Infrastructure\Observability\CorrelationContext;
use EasyPrint\Infrastructure\Observability\JsonLineLogger;
use EasyPrint\Infrastructure\Process\ProcessFailureReason;
use EasyPrint\Infrastructure\Process\ProcessResult;
use EasyPrint\Tests\Support\FakeProcessRunner;

use function fclose;
use function fopen;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function rewind;
use function stream_get_contents;

final class LpoptionsCapabilityDiscoveryTest extends TestCase
{
    public function testItUsesSeparatedArgumentsAndReturnsAdvertisedCapabilities(): void
    {
        $runner = new FakeProcessRunner([$this->processResult(<<<'OUTPUT'
            PageSize/Media Size: *A4 Letter
            Duplex/Two-Sided: *None DuplexNoTumble
            VendorMode/Vendor Mode: *One Two
            OUTPUT)]);
        $discovery = $this->discovery($runner, requireEncryption: true);

        $snapshot = $discovery->discover('REFERENCE_QUEUE');

        self::assertSame(CupsConnectivity::Available, $snapshot->connectivity);
        self::assertSame('REFERENCE_QUEUE', $snapshot->queueIdentifier);
        self::assertCount(3, $snapshot->options);
        self::assertCount(2, $snapshot->renderableOptions());
        self::assertCount(1, $snapshot->unknownOptions());
        self::assertSame(CapabilityCategory::Sides, $snapshot->options[1]->category);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', (string) $snapshot->fingerprint);
        self::assertSame([[
            'executableKey' => 'lpoptions',
            'arguments' => ['-E', '-h', '[2001:db8::20]:8631', '-p', 'REFERENCE_QUEUE', '-l'],
            'environmentOverrides' => [],
        ]], $runner->calls);
    }

    #[DataProvider('failureProvider')]
    public function testItMapsProcessAndParserFailures(CupsConnectivity $expected, ProcessResult $result): void
    {
        $snapshot = $this->discovery(new FakeProcessRunner([$result]))->discover('REFERENCE_QUEUE');

        self::assertSame($expected, $snapshot->connectivity);
        self::assertSame([], $snapshot->options);
        self::assertNull($snapshot->fingerprint);
    }

    /**
     * @return iterable<string,array{CupsConnectivity,ProcessResult}>
     */
    public static function failureProvider(): iterable
    {
        yield 'timeout' => [
            CupsConnectivity::TimedOut,
            self::failed(ProcessFailureReason::TimedOut),
        ];
        yield 'bounded output exceeded' => [
            CupsConnectivity::MalformedResponse,
            self::failed(ProcessFailureReason::OutputLimit),
        ];
        yield 'unauthorized' => [
            CupsConnectivity::Unauthorized,
            self::failed(ProcessFailureReason::NonZeroExit, 'client-error-not-authorized'),
        ];
        yield 'unavailable' => [
            CupsConnectivity::Unavailable,
            self::failed(ProcessFailureReason::NonZeroExit, 'scheduler unavailable'),
        ];
        yield 'malformed successful output' => [
            CupsConnectivity::MalformedResponse,
            self::successful('not an option'),
        ];
    }

    public function testItRejectsAQueueIdentifierThatCannotComeFromCupsDiscovery(): void
    {
        $runner = new FakeProcessRunner([]);
        $this->expectException(InvalidArgumentException::class);

        $this->discovery($runner)->discover("queue\n--help");
    }

    public function testOperationalLogsContainCountsButNoQueueOrDriverIdentifiers(): void
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        $runner = new FakeProcessRunner([$this->processResult(
            'PageSize/Media Size: *A4 Letter' . "\n" . 'VendorSecret/Private Driver Label: *Off On',
        )]);

        $this->discovery(
            $runner,
            logger: new JsonLineLogger(new CorrelationContext(), $stream),
        )->discover('PRIVATE_QUEUE');

        rewind($stream);
        $log = stream_get_contents($stream);
        self::assertIsString($log);
        self::assertStringContainsString('cups.capability_discovery.completed', $log);
        self::assertStringContainsString('"option_count":2', $log);
        self::assertStringContainsString('"unknown_option_count":1', $log);
        self::assertStringNotContainsString('PRIVATE_QUEUE', $log);
        self::assertStringNotContainsString('VendorSecret', $log);
        self::assertStringNotContainsString('Private Driver Label', $log);
        fclose($stream);
    }

    private function discovery(
        FakeProcessRunner $runner,
        bool $requireEncryption = false,
        LoggerInterface $logger = new NullLogger(),
    ): LpoptionsCapabilityDiscovery {
        return new LpoptionsCapabilityDiscovery(
            processRunner: $runner,
            parser: new LpoptionsOutputParser(),
            host: '2001:db8::20',
            port: 8631,
            requireEncryption: $requireEncryption,
            logger: $logger,
        );
    }

    private function processResult(string $stdout): ProcessResult
    {
        return self::successful($stdout);
    }

    private static function successful(string $stdout): ProcessResult
    {
        return new ProcessResult('lpoptions', $stdout, '', 0, 1, null);
    }

    private static function failed(ProcessFailureReason $reason, string $stderr = ''): ProcessResult
    {
        return new ProcessResult('lpoptions', '', $stderr, 1, 1, $reason);
    }
}
