<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Unit\Infrastructure\Cups;

use EasyPrint\Infrastructure\Cups\LpSubmissionOutputParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LpSubmissionOutputParserTest extends TestCase
{
    #[DataProvider('outputProvider')]
    public function testItParsesOnlyTheExpectedQueueAndSingleFileResponse(string $output, ?int $expected): void
    {
        self::assertSame($expected, new LpSubmissionOutputParser()->cupsJobId($output, 'QUEUE.with-special'));
    }

    /**
     * @return iterable<string,array{string,?int}>
     */
    public static function outputProvider(): iterable
    {
        yield 'accepted response' => ["request id is QUEUE.with-special-42 (1 file(s))\n", 42];
        yield 'windows newline' => ["request id is QUEUE.with-special-42 (1 file(s))\r\n", 42];
        yield 'wrong queue' => ["request id is OTHER_QUEUE-42 (1 file(s))\n", null];
        yield 'zero identifier' => ["request id is QUEUE.with-special-0 (1 file(s))\n", null];
        yield 'multiple files' => ["request id is QUEUE.with-special-42 (2 file(s))\n", null];
        yield 'extra output' => ["notice\nrequest id is QUEUE.with-special-42 (1 file(s))\n", null];
        yield 'overflowing identifier' => ["request id is QUEUE.with-special-999999999999999999999 (1 file(s))\n", null];
    }
}
