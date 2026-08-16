<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Process;

use function array_fill_keys;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function getenv;
use function in_array;

use InvalidArgumentException;

use function is_array;
use function is_dir;
use function is_string;
use function microtime;
use function str_contains;
use function strlen;
use function substr;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

final readonly class AllowedProcessRunner implements ProcessRunner
{
    /**
     * @param array<string,string> $allowedExecutables
     * @param array<string,string> $environment
     * @param list<string>         $allowedEnvironmentOverrides
     */
    public function __construct(
        private array $allowedExecutables,
        private string $workingDirectory,
        private float $timeoutSeconds,
        private int $maximumOutputBytes,
        private array $environment = ['LANG' => 'C', 'LC_ALL' => 'C', 'PATH' => '/usr/bin:/bin'],
        private array $allowedEnvironmentOverrides = [],
    ) {
        if ($timeoutSeconds <= 0) {
            throw new InvalidArgumentException('The process timeout must be greater than zero.');
        }

        if ($maximumOutputBytes < 1) {
            throw new InvalidArgumentException('The process output limit must be greater than zero.');
        }
    }

    /**
     * @param list<string>         $arguments
     * @param array<string,string> $environmentOverrides
     */
    public function run(string $executableKey, array $arguments = [], array $environmentOverrides = []): ProcessResult
    {
        $startedAt = microtime(true);

        if (!array_key_exists($executableKey, $this->allowedExecutables)) {
            return $this->failure($executableKey, $startedAt, ProcessFailureReason::NotAllowed);
        }

        if (!is_dir($this->workingDirectory)) {
            return $this->failure($executableKey, $startedAt, ProcessFailureReason::StartFailed);
        }

        foreach ($arguments as $argument) {
            if (!is_string($argument) || str_contains($argument, "\0")) {
                return $this->failure($executableKey, $startedAt, ProcessFailureReason::InvalidArgument);
            }
        }

        foreach ($environmentOverrides as $name => $value) {
            if (!is_string($name) || !is_string($value) || !in_array($name, $this->allowedEnvironmentOverrides, true) || str_contains($name . $value, "\0")) {
                return $this->failure($executableKey, $startedAt, ProcessFailureReason::InvalidArgument);
            }
        }

        $command = [$this->allowedExecutables[$executableKey], ...$arguments];
        $environment = self::isolatedEnvironment(array_merge($this->environment, $environmentOverrides));
        $process = new Process($command, $this->workingDirectory, $environment, null, $this->timeoutSeconds);
        $stdout = '';
        $stderr = '';
        $capturedBytes = 0;
        $failureReason = null;
        $exitCode = null;

        try {
            /**
             * @throws OutputLimitExceeded
             * @throws ProcessTimedOutException
             * @throws Throwable
             */
            $exitCode = $process->run(function (string $type, string $chunk) use (&$stdout, &$stderr, &$capturedBytes): void {
                $target = Process::OUT === $type ? $stdout : $stderr;
                $truncated = self::appendBounded($target, $chunk, $capturedBytes, $this->maximumOutputBytes);

                if (Process::OUT === $type) {
                    $stdout = $target;
                } else {
                    $stderr = $target;
                }

                if ($truncated) {
                    throw new OutputLimitExceeded();
                }
            });
        } catch (ProcessTimedOutException) {
            $failureReason = ProcessFailureReason::TimedOut;
            $process->stop(0.1);
            $exitCode = $process->getExitCode();
        } catch (OutputLimitExceeded) {
            $failureReason = ProcessFailureReason::OutputLimit;
            $process->stop(0.1);
            $exitCode = $process->getExitCode();
        } catch (Throwable) {
            $failureReason = ProcessFailureReason::StartFailed;
            $process->stop(0.1);
            $exitCode = $process->getExitCode();
        }

        if (null === $failureReason && 0 !== $exitCode) {
            $failureReason = ProcessFailureReason::NonZeroExit;
        }

        return new ProcessResult(
            executableKey: $executableKey,
            stdout: $stdout,
            stderr: $stderr,
            exitCode: $exitCode,
            durationMilliseconds: self::durationMilliseconds($startedAt),
            failureReason: $failureReason,
        );
    }

    private function failure(string $executableKey, float $startedAt, ProcessFailureReason $reason): ProcessResult
    {
        return new ProcessResult(
            executableKey: $executableKey,
            stdout: '',
            stderr: '',
            exitCode: null,
            durationMilliseconds: self::durationMilliseconds($startedAt),
            failureReason: $reason,
        );
    }

    private static function durationMilliseconds(float $startedAt): int
    {
        return (int) ((microtime(true) - $startedAt) * 1_000);
    }

    /**
     * Symfony Process merges the supplied environment with the parent process.
     * False values explicitly remove inherited names, leaving only the bounded
     * environment selected by the application.
     *
     * @param array<string,string> $environment
     *
     * @return array<string,string|false>
     */
    private static function isolatedEnvironment(array $environment): array
    {
        $inherited = getenv();
        $isolated = is_array($inherited) ? array_fill_keys(array_keys($inherited), false) : [];

        foreach ($environment as $name => $value) {
            $isolated[$name] = $value;
        }

        return $isolated;
    }

    private static function appendBounded(string &$target, string $chunk, int &$capturedBytes, int $maximumOutputBytes): bool
    {
        if ('' === $chunk) {
            return false;
        }

        $remaining = $maximumOutputBytes - $capturedBytes;

        if ($remaining <= 0) {
            return true;
        }

        $truncated = strlen($chunk) > $remaining;
        $bounded = $truncated ? substr($chunk, 0, $remaining) : $chunk;
        $target .= $bounded;
        $capturedBytes += strlen($bounded);

        return $truncated;
    }
}
