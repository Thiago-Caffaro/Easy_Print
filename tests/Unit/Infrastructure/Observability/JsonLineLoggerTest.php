<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Unit\Infrastructure\Observability;

use EasyPrint\Infrastructure\Observability\CorrelationContext;
use EasyPrint\Infrastructure\Observability\JsonLineLogger;

use function fclose;
use function fgets;
use function fopen;
use function is_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

use PHPUnit\Framework\TestCase;

use function rewind;

use RuntimeException;

use function strlen;

final class JsonLineLoggerTest extends TestCase
{
    public function testItEmitsBoundedStructuredAndRedactedContext(): void
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        $context = new CorrelationContext();
        $context->begin('0123456789abcdef0123456789abcdef');
        $logger = new JsonLineLogger($context, $stream, 'info');

        $logger->debug('not.emitted');
        $logger->info('adapter.completed', [
            'component' => 'cups',
            'connectivity' => 'available',
            'option_count' => 8,
            'queue_count' => 2,
            'unknown_option_count' => 1,
            'document_title' => 'private.pdf',
            'authorization' => 'Bearer private',
            'exception' => new RuntimeException('private exception message'),
            'nested' => ['private'],
        ]);

        rewind($stream);
        $line = fgets($stream);
        self::assertIsString($line);
        $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($record);
        self::assertSame('info', $record['level']);
        self::assertSame('adapter.completed', $record['event']);
        self::assertSame('0123456789abcdef0123456789abcdef', $record['correlation_id']);
        self::assertSame('cups', $record['context']['component']);
        self::assertSame('available', $record['context']['connectivity']);
        self::assertSame(8, $record['context']['option_count']);
        self::assertSame(2, $record['context']['queue_count']);
        self::assertSame(1, $record['context']['unknown_option_count']);
        self::assertSame('[redacted]', $record['context']['document_title']);
        self::assertSame('[redacted]', $record['context']['authorization']);
        self::assertSame(RuntimeException::class, $record['context']['exception']);
        self::assertSame('[redacted]', $record['context']['nested']);
        self::assertLessThan(4_096, strlen($line));
        self::assertFalse(fgets($stream));
        $context->end('0123456789abcdef0123456789abcdef');
        fclose($stream);
    }

    public function testAClosedDestinationCannotChangeTheApplicationOutcome(): void
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        $logger = new JsonLineLogger(new CorrelationContext(), $stream);
        fclose($stream);

        $logger->error('log.write.failed');

        self::addToAssertionCount(1);
    }

    public function testItRejectsFreeFormEventMessages(): void
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        $logger = new JsonLineLogger(new CorrelationContext(), $stream);

        $logger->warning('private message at /private/path');
        rewind($stream);
        $line = fgets($stream);
        self::assertIsString($line);

        self::assertStringContainsString('"event":"log.invalid_event"', $line);
        self::assertStringNotContainsString('private message', $line);
        self::assertStringNotContainsString('/private/path', $line);
        fclose($stream);
    }

    public function testItRedactsInvalidValuesForKnownContextKeys(): void
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        $logger = new JsonLineLogger(new CorrelationContext(), $stream);

        $logger->warning('context.rejected', [
            'component' => '/private/path',
            'connectivity' => 'secret-state',
            'duration_ms' => -1,
            'method' => "GET\nAuthorization: secret",
            'queue_count' => 10_001,
            'status' => 999,
        ]);
        rewind($stream);
        $line = fgets($stream);
        self::assertIsString($line);
        $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($record);

        self::assertSame([
            'component' => '[redacted]',
            'connectivity' => '[redacted]',
            'duration_ms' => '[redacted]',
            'method' => '[redacted]',
            'queue_count' => '[redacted]',
            'status' => '[redacted]',
        ], $record['context']);
        self::assertStringNotContainsString('private', $line);
        self::assertStringNotContainsString('secret', $line);
        fclose($stream);
    }
}
