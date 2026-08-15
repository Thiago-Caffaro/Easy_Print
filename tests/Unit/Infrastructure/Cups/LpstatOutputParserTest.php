<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Unit\Infrastructure\Cups;

use EasyPrint\Domain\Printer\PrinterState;
use EasyPrint\Infrastructure\Cups\LpstatOutputParser;
use EasyPrint\Infrastructure\Cups\MalformedLpstatOutput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LpstatOutputParserTest extends TestCase
{
    private LpstatOutputParser $parser;

    protected function setUp(): void
    {
        $this->parser = new LpstatOutputParser();
    }

    public function testItDistinguishesRunningAndStoppedSchedulers(): void
    {
        self::assertTrue($this->parser->schedulerIsRunning("scheduler is running\n"));
        self::assertFalse($this->parser->schedulerIsRunning("scheduler is not running\n"));
    }

    public function testItParsesAConfiguredDefaultAndTheAbsenceOfOne(): void
    {
        self::assertSame('REFERENCE_QUEUE', $this->parser->defaultQueueIdentifier("system default destination: REFERENCE_QUEUE\n"));
        self::assertNull($this->parser->defaultQueueIdentifier("no system default destination\n"));
    }

    public function testItPreservesOpaqueQueueIdentifiersAndTheirOrder(): void
    {
        self::assertSame(
            ['REFERENCE_QUEUE', 'WAREHOUSE_LABELS'],
            $this->parser->queueIdentifiers("REFERENCE_QUEUE\r\nWAREHOUSE_LABELS\r\n"),
        );
    }

    public function testAnEmptyQueueListIsValid(): void
    {
        self::assertSame([], $this->parser->queueIdentifiers(''));
    }

    public function testItNormalizesKnownQueueStatesAndMarksMissingQueuesUnavailable(): void
    {
        $states = $this->parser->queueStates(
            "printer READY is idle. enabled since date\n"
            . "printer BUSY now printing BUSY-42. enabled since date\n"
            . "printer STOPPED disabled since date - reason\n"
            . "printer MYSTERY has a future state\n",
            ['READY', 'BUSY', 'STOPPED', 'MYSTERY', 'REMOVED'],
        );

        self::assertSame([
            'READY' => PrinterState::Ready,
            'BUSY' => PrinterState::Processing,
            'STOPPED' => PrinterState::Stopped,
            'MYSTERY' => PrinterState::Unknown,
            'REMOVED' => PrinterState::Unavailable,
        ], $states);
    }

    #[DataProvider('malformedOutputProvider')]
    public function testItRejectsMalformedOutput(string $operation, string $output): void
    {
        $this->expectException(MalformedLpstatOutput::class);
        $this->parser->{$operation}($output);
    }

    /**
     * @return iterable<string,array{string,string}>
     */
    public static function malformedOutputProvider(): iterable
    {
        yield 'unknown scheduler response' => ['schedulerIsRunning', 'service maybe running'];
        yield 'empty default identifier' => ['defaultQueueIdentifier', 'system default destination: '];
        yield 'duplicate queue' => ['queueIdentifiers', "DUPLICATE\nDUPLICATE\n"];
        yield 'control character' => ['queueIdentifiers', "SAFE\nBAD\tQUEUE\n"];
    }
}
