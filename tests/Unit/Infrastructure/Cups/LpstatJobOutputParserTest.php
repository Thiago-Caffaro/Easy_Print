<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Unit\Infrastructure\Cups;

use EasyPrint\Infrastructure\Cups\LpstatJobOutputParser;
use EasyPrint\Infrastructure\Cups\MalformedLpstatOutput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LpstatJobOutputParserTest extends TestCase
{
    public function testItParsesBoundedRowsFromTheRightWithoutDependingOnPadding(): void
    {
        $output = "QUEUE-WITH-HYPHEN-123 owner with spaces  4096   Wed 13 Aug 2026 10:30:00 AM UTC\n";

        $jobs = new LpstatJobOutputParser()->activeJobs($output);

        self::assertCount(1, $jobs);
        self::assertSame('QUEUE-WITH-HYPHEN', $jobs[0]->queueIdentifier);
        self::assertSame(123, $jobs[0]->cupsJobId);
        self::assertSame(4096, $jobs[0]->byteSize);
        self::assertSame('Wed 13 Aug 2026 10:30:00 AM UTC', $jobs[0]->submittedAtLabel);
        self::assertSame('unknown', $jobs[0]->state->value);
    }

    public function testEmptyOutputIsAnAvailableEmptyList(): void
    {
        self::assertSame([], new LpstatJobOutputParser()->activeJobs("\n"));
    }

    #[DataProvider('malformedProvider')]
    public function testItRejectsMalformedOrUnboundedRows(string $output): void
    {
        $this->expectException(MalformedLpstatOutput::class);

        new LpstatJobOutputParser()->activeJobs($output);
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function malformedProvider(): iterable
    {
        yield 'missing job identifier' => ["REFERENCE_QUEUE owner 1024   Wed 13 Aug 2026 10:30:00 AM UTC\n"];
        yield 'zero job identifier' => ["REFERENCE_QUEUE-0 owner 1024   Wed 13 Aug 2026 10:30:00 AM UTC\n"];
        yield 'slash in queue' => ["QUEUE/INSTANCE-1 owner 1024   Wed 13 Aug 2026 10:30:00 AM UTC\n"];
        yield 'negative bytes' => ["REFERENCE_QUEUE-1 owner -1   Wed 13 Aug 2026 10:30:00 AM UTC\n"];
        yield 'control in date' => ["REFERENCE_QUEUE-1 owner 1   Wed 13 Aug\t2026 10:30:00 AM UTC\n"];
        yield 'unexpected row' => ["private unexpected output\n"];
        yield 'duplicate request identifier' => [
            "REFERENCE_QUEUE-1 owner 1   Wed 13 Aug 2026 10:30:00 AM UTC\n"
            . "REFERENCE_QUEUE-1 owner 1   Wed 13 Aug 2026 10:31:00 AM UTC\n",
        ];
    }
}
