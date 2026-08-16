<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Integration\Infrastructure\Cups;

use function dirname;

use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Infrastructure\Cups\LpstatOutputParser;
use EasyPrint\Infrastructure\Cups\LpstatQueueDiscovery;
use EasyPrint\Infrastructure\Observability\CorrelationContext;
use EasyPrint\Infrastructure\Observability\JsonLineLogger;
use EasyPrint\Infrastructure\Process\ProcessFailureReason;
use EasyPrint\Infrastructure\Process\ProcessResult;
use EasyPrint\Tests\Support\FakeProcessRunner;

use function fclose;
use function file_get_contents;
use function fopen;
use function is_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function rewind;
use function stream_get_contents;

final class LpstatQueueDiscoveryTest extends TestCase
{
    #[DataProvider('availableFixtureProvider')]
    public function testItDiscoversQueuesFromContractFixtures(string $fixtureName): void
    {
        $fixture = $this->fixture($fixtureName);
        $runner = new FakeProcessRunner([
            $this->success($fixture['commands']['scheduler']['stdout']),
            $this->success($fixture['commands']['default']['stdout']),
            $this->success($fixture['commands']['queues']['stdout']),
            $this->success($fixture['commands']['states']['stdout']),
        ]);

        $snapshot = $this->discovery($runner)->discover();

        self::assertSame($fixture['expected']['connectivity'], $snapshot->connectivity->value);
        self::assertSame($fixture['expected']['defaultQueueIdentifier'], $snapshot->defaultQueueIdentifier);
        self::assertSame($fixture['expected']['queues'], array_map(static fn($queue): array => [
            'identifier' => $queue->identifier,
            'state' => $queue->state->value,
        ], $snapshot->queues));
        $expectedCalls = [
            ['executableKey' => 'lpstat', 'arguments' => ['-h', 'cups.internal:631', '-r'], 'environmentOverrides' => []],
            ['executableKey' => 'lpstat', 'arguments' => ['-h', 'cups.internal:631', '-d'], 'environmentOverrides' => []],
            ['executableKey' => 'lpstat', 'arguments' => ['-h', 'cups.internal:631', '-e'], 'environmentOverrides' => []],
        ];

        if ([] !== $fixture['expected']['queues']) {
            $expectedCalls[] = ['executableKey' => 'lpstat', 'arguments' => ['-h', 'cups.internal:631', '-p'], 'environmentOverrides' => []];
        }

        self::assertSame($expectedCalls, $runner->calls);
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function availableFixtureProvider(): iterable
    {
        yield 'multiple non-device-specific queues' => ['multiple-queues'];
        yield 'available server with no queues' => ['empty-queues'];
    }

    public function testItMapsAStoppedSchedulerToUnavailableWithoutFurtherCalls(): void
    {
        $runner = new FakeProcessRunner([$this->success("scheduler is not running\n")]);

        $snapshot = $this->discovery($runner)->discover();

        self::assertSame(CupsConnectivity::Unavailable, $snapshot->connectivity);
        self::assertCount(1, $runner->calls);
    }

    #[DataProvider('failureProvider')]
    public function testItMapsProcessFailuresToStructuredConnectivity(ProcessResult $failure, CupsConnectivity $expected): void
    {
        $runner = new FakeProcessRunner([$failure]);

        $snapshot = $this->discovery($runner)->discover();

        self::assertSame($expected, $snapshot->connectivity);
        self::assertSame([], $snapshot->queues);
        self::assertNull($snapshot->defaultQueueIdentifier);
    }

    /**
     * @return iterable<string,array{ProcessResult,CupsConnectivity}>
     */
    public static function failureProvider(): iterable
    {
        yield 'timeout' => [self::failure(ProcessFailureReason::TimedOut), CupsConnectivity::TimedOut];
        yield 'unauthorized' => [self::failure(ProcessFailureReason::NonZeroExit, 'lpstat: Unauthorized'), CupsConnectivity::Unauthorized];
        yield 'unavailable' => [self::failure(ProcessFailureReason::NonZeroExit, 'lpstat: Scheduler is not responding.'), CupsConnectivity::Unavailable];
        yield 'bounded output' => [self::failure(ProcessFailureReason::OutputLimit), CupsConnectivity::MalformedResponse];
    }

    public function testItMapsSuccessfulButMalformedOutputWithoutExposingIt(): void
    {
        $runner = new FakeProcessRunner([$this->success('unexpected output containing private details')]);

        $snapshot = $this->discovery($runner)->discover();

        self::assertSame(CupsConnectivity::MalformedResponse, $snapshot->connectivity);
        self::assertSame([], $snapshot->queues);
    }

    public function testItRequestsEncryptionAndFormatsAnIpv6ServerAddress(): void
    {
        $runner = new FakeProcessRunner([$this->success("scheduler is not running\n")]);
        $discovery = new LpstatQueueDiscovery(
            processRunner: $runner,
            parser: new LpstatOutputParser(),
            host: '2001:db8::10',
            port: 8631,
            requireEncryption: true,
        );

        $discovery->discover();

        self::assertSame(['-E', '-h', '[2001:db8::10]:8631', '-r'], $runner->calls[0]['arguments']);
    }

    public function testAStateLookupFailureKeepsConnectivityAndMarksTheQueueUnavailable(): void
    {
        $runner = new FakeProcessRunner([
            $this->success("scheduler is running\n"),
            $this->success("system default destination: REFERENCE_QUEUE\n"),
            $this->success("REFERENCE_QUEUE\n"),
            self::failure(ProcessFailureReason::TimedOut),
        ]);

        $snapshot = $this->discovery($runner)->discover();

        self::assertSame(CupsConnectivity::Available, $snapshot->connectivity);
        self::assertSame('unavailable', $snapshot->queues[0]->state->value);
    }

    public function testAdapterLogsCarryCorrelationWithoutCupsOutputOrServerDetails(): void
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        $context = new CorrelationContext();
        $context->begin('0123456789abcdef0123456789abcdef');
        $runner = new FakeProcessRunner([
            self::failure(ProcessFailureReason::NonZeroExit, 'private scheduler output at cups.internal'),
        ]);
        $discovery = new LpstatQueueDiscovery(
            processRunner: $runner,
            parser: new LpstatOutputParser(),
            host: 'cups.internal',
            port: 631,
            requireEncryption: false,
            logger: new JsonLineLogger($context, $stream),
        );

        $discovery->discover();
        rewind($stream);
        $log = stream_get_contents($stream);
        self::assertIsString($log);

        self::assertStringContainsString('0123456789abcdef0123456789abcdef', $log);
        self::assertStringContainsString('cups.queue_discovery.completed', $log);
        self::assertStringContainsString('"connectivity":"unavailable"', $log);
        self::assertStringContainsString('"queue_count":0', $log);
        self::assertStringNotContainsString('private scheduler output', $log);
        self::assertStringNotContainsString('cups.internal', $log);

        $context->end('0123456789abcdef0123456789abcdef');
        fclose($stream);
    }

    private function discovery(FakeProcessRunner $runner): LpstatQueueDiscovery
    {
        return new LpstatQueueDiscovery(
            processRunner: $runner,
            parser: new LpstatOutputParser(),
            host: 'cups.internal',
            port: 631,
            requireEncryption: false,
        );
    }

    /**
     * @return array{
     *   commands:array{scheduler:array{stdout:string},default:array{stdout:string},queues:array{stdout:string},states:array{stdout:string}},
     *   expected:array{connectivity:string,defaultQueueIdentifier:?string,queues:list<array{identifier:string,state:string}>}
     * }
     */
    private function fixture(string $name): array
    {
        $path = dirname(__DIR__, 3) . '/Fixtures/Cups/Contract/queue-discovery/' . $name . '.json';
        $contents = file_get_contents($path);
        self::assertIsString($contents);
        $fixture = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($fixture);

        /** @var array{
         *   commands:array{scheduler:array{stdout:string},default:array{stdout:string},queues:array{stdout:string},states:array{stdout:string}},
         *   expected:array{connectivity:string,defaultQueueIdentifier:?string,queues:list<array{identifier:string,state:string}>}
         * } $fixture
         */
        return $fixture;
    }

    private function success(string $stdout): ProcessResult
    {
        return new ProcessResult('lpstat', $stdout, '', 0, 1, null);
    }

    private static function failure(ProcessFailureReason $reason, string $stderr = ''): ProcessResult
    {
        return new ProcessResult('lpstat', '', $stderr, 1, 1, $reason);
    }
}
