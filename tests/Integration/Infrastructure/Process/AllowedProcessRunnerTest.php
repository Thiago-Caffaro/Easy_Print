<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Integration\Infrastructure\Process;

use function array_filter;
use function dirname;

use EasyPrint\Infrastructure\Process\AllowedProcessRunner;
use EasyPrint\Infrastructure\Process\ProcessFailureReason;

use function getenv;
use function json_decode;

use const JSON_THROW_ON_ERROR;

use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;

final class AllowedProcessRunnerTest extends TestCase
{
    private string $fixtures;

    /** @var array<string,string> */
    private array $environment;

    protected function setUp(): void
    {
        $this->fixtures = dirname(__DIR__, 3) . '/Fixtures/Process';
        $this->environment = ['LANG' => 'C', 'LC_ALL' => 'C'];

        foreach (['PATH', 'SystemRoot', 'SYSTEMROOT'] as $name) {
            $value = getenv($name);

            if (false !== $value) {
                $this->environment[$name] = $value;
            }
        }
    }

    public function testItPreservesArgumentBoundariesWithoutInvokingAShell(): void
    {
        $runner = $this->runner();
        $arguments = [
            $this->fixtures . '/echo-arguments.php',
            'plain value',
            '; echo not-executed',
            '$(whoami)',
        ];

        $result = $runner->run('php', $arguments);

        self::assertTrue($result->succeeded());
        self::assertSame(array_slice($arguments, 1), json_decode($result->stdout, true, flags: JSON_THROW_ON_ERROR));
        self::assertSame('', $result->stderr);
    }

    public function testItRejectsExecutablesOutsideTheAllowlist(): void
    {
        $result = $this->runner()->run('powershell', ['-Command', 'whoami']);

        self::assertSame(ProcessFailureReason::NotAllowed, $result->failureReason);
        self::assertNull($result->exitCode);
    }

    public function testItTerminatesAProcessAfterTheDeadline(): void
    {
        $runner = $this->runner(timeoutSeconds: 0.05);

        $result = $runner->run('php', [$this->fixtures . '/sleep.php', '500000']);

        self::assertSame(ProcessFailureReason::TimedOut, $result->failureReason);
        self::assertLessThan(1_000, $result->durationMilliseconds);
    }

    public function testItBoundsCombinedStandardOutputAndError(): void
    {
        $runner = $this->runner(maximumOutputBytes: 32);

        $result = $runner->run('php', [$this->fixtures . '/large-output.php', '4096']);

        self::assertSame(ProcessFailureReason::OutputLimit, $result->failureReason);
        self::assertSame(32, strlen($result->stdout . $result->stderr));
    }

    public function testItReturnsANonZeroExitAsAStructuredFailure(): void
    {
        $result = $this->runner()->run('php', [$this->fixtures . '/exit.php', '7', 'safe failure']);

        self::assertSame(ProcessFailureReason::NonZeroExit, $result->failureReason);
        self::assertSame(7, $result->exitCode);
        self::assertSame('safe failure', $result->stderr);
    }

    private function runner(float $timeoutSeconds = 2.0, int $maximumOutputBytes = 16_384): AllowedProcessRunner
    {
        return new AllowedProcessRunner(
            allowedExecutables: ['php' => PHP_BINARY],
            workingDirectory: sys_get_temp_dir(),
            timeoutSeconds: $timeoutSeconds,
            maximumOutputBytes: $maximumOutputBytes,
            environment: $this->environment,
        );
    }
}
