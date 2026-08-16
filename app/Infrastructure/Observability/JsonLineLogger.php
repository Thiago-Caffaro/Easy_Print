<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Observability;

use function array_key_exists;
use function array_slice;
use function fopen;
use function fwrite;
use function gmdate;
use function in_array;

use InvalidArgumentException;

use function is_int;
use function is_resource;
use function is_string;
use function json_encode;

use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_THROW_ON_ERROR;
use const PHP_EOL;

use function preg_match;
use function preg_replace;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use RuntimeException;
use Stringable;

use function strlen;
use function substr;

use Throwable;

final class JsonLineLogger extends AbstractLogger
{
    private const CONTEXT_LIMIT = 12;
    private const EVENT_BYTES = 128;
    private const KEY_BYTES = 64;
    private const SAFE_CONTEXT_KEYS = [
        'component',
        'connectivity',
        'duration_ms',
        'exception',
        'job_count',
        'method',
        'option_count',
        'queue_count',
        'reason_count',
        'status',
        'unknown_option_count',
    ];
    private const PRIORITIES = [
        LogLevel::DEBUG => 100,
        LogLevel::INFO => 200,
        LogLevel::NOTICE => 250,
        LogLevel::WARNING => 300,
        LogLevel::ERROR => 400,
        LogLevel::CRITICAL => 500,
        LogLevel::ALERT => 550,
        LogLevel::EMERGENCY => 600,
    ];

    /** @var resource */
    private $stream;

    /**
     * @param resource $stream
     */
    public function __construct(
        private readonly CorrelationContext $correlation,
        $stream,
        private readonly string $minimumLevel = LogLevel::INFO,
    ) {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException('The log destination must be a writable stream resource.');
        }

        if (!array_key_exists($minimumLevel, self::PRIORITIES)) {
            throw new InvalidArgumentException('The minimum log level is unsupported.');
        }

        $this->stream = $stream;
    }

    public static function toStderr(CorrelationContext $correlation, string $minimumLevel = LogLevel::INFO): self
    {
        $stream = fopen('php://stderr', 'wb');

        if (false === $stream) {
            throw new RuntimeException('The structured log stream could not be opened.');
        }

        return new self($correlation, $stream, $minimumLevel);
    }

    /**
     * @param array<string,mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (!is_string($level) || !array_key_exists($level, self::PRIORITIES)) {
            throw new InvalidArgumentException('The log level is unsupported.');
        }

        if (self::PRIORITIES[$level] < self::PRIORITIES[$this->minimumLevel]) {
            return;
        }

        try {
            $event = $this->boundedString((string) $message, self::EVENT_BYTES);

            if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]*$/D', $event)) {
                $event = 'log.invalid_event';
            }

            $record = [
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'level' => $level,
                'event' => $event,
            ];
            $correlationId = $this->correlation->current();

            if (null !== $correlationId) {
                $record['correlation_id'] = $correlationId;
            }

            $sanitized = $this->sanitizeContext($context);

            if ([] !== $sanitized) {
                $record['context'] = $sanitized;
            }

            $line = json_encode($record, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL;
            @fwrite($this->stream, $line);
        } catch (Throwable) {
            // Logging must never alter the request or adapter outcome.
        }
    }

    /**
     * @param array<string,mixed> $context
     *
     * @return array<string, int|string>
     */
    private function sanitizeContext(array $context): array
    {
        $sanitized = [];

        foreach (array_slice($context, 0, self::CONTEXT_LIMIT, true) as $key => $value) {
            $key = $this->boundedString((string) $key, self::KEY_BYTES);

            if (!in_array($key, self::SAFE_CONTEXT_KEYS, true)) {
                $sanitized[$key] = '[redacted]';

                continue;
            }

            if ('exception' === $key) {
                $class = $value instanceof Throwable ? $value::class : '';
                $sanitized[$key] = 1 === preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/D', $class)
                    ? $class
                    : '[redacted]';

                continue;
            }

            $sanitized[$key] = match ($key) {
                'component' => is_string($value) && in_array($value, ['cups', 'database', 'storage'], true)
                    ? $value
                    : '[redacted]',
                'connectivity' => is_string($value) && in_array($value, [
                    'available',
                    'malformed_response',
                    'timed_out',
                    'unauthorized',
                    'unavailable',
                ], true) ? $value : '[redacted]',
                'method' => is_string($value) && 1 === preg_match('/^[A-Z]{3,16}$/D', $value)
                    ? $value
                    : '[redacted]',
                'status' => is_int($value) && $value >= 100 && $value <= 599
                    ? $value
                    : '[redacted]',
                'duration_ms' => is_int($value) && $value >= 0 && $value <= 86_400_000
                    ? $value
                    : '[redacted]',
                'job_count', 'option_count', 'queue_count', 'reason_count', 'unknown_option_count' => is_int($value)
                    && $value >= 0 && $value <= 10_000
                    ? $value
                    : '[redacted]',
            };
        }

        return $sanitized;
    }

    private function boundedString(string $value, int $maximumBytes): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/', '?', $value) ?? '';

        if (strlen($value) <= $maximumBytes) {
            return $value;
        }

        return substr($value, 0, $maximumBytes - 3) . '...';
    }
}
