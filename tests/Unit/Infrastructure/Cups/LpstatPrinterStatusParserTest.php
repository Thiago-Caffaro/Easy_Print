<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Unit\Infrastructure\Cups;

use EasyPrint\Domain\Printer\PrinterState;
use EasyPrint\Infrastructure\Cups\LpstatPrinterStatusParser;
use EasyPrint\Infrastructure\Cups\MalformedLpstatOutput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LpstatPrinterStatusParserTest extends TestCase
{
    public function testItParsesStateReasonsAndAcceptingStatus(): void
    {
        $parser = new LpstatPrinterStatusParser();
        [$state, $reasons] = $parser->status(
            "printer REFERENCE_QUEUE is idle. enabled since date\n\tDescription: Office\n\tAlerts: media-needed-error cover-open\n",
            'REFERENCE_QUEUE',
        );

        self::assertSame(PrinterState::Ready, $state);
        self::assertSame(['media-needed-error', 'cover-open'], $reasons);
        self::assertTrue($parser->accepting('REFERENCE_QUEUE accepting requests since date', 'REFERENCE_QUEUE'));
        self::assertFalse($parser->accepting('REFERENCE_QUEUE not accepting requests since date', 'REFERENCE_QUEUE'));
    }

    #[DataProvider('invalidStatusProvider')]
    public function testItRejectsMismatchedOrUnsafeStatus(string $output): void
    {
        $this->expectException(MalformedLpstatOutput::class);

        new LpstatPrinterStatusParser()->status($output, 'REFERENCE_QUEUE');
    }

    /** @return iterable<string,array{string}> */
    public static function invalidStatusProvider(): iterable
    {
        yield 'wrong queue' => ['printer OTHER is idle. enabled since date'];
        yield 'unsafe reason' => ["printer REFERENCE_QUEUE is idle. enabled since date\n\tAlerts: <script>"];
    }
}
