<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Support;

use function array_shift;

use EasyPrint\Infrastructure\Process\ProcessResult;
use EasyPrint\Infrastructure\Process\ProcessRunner;
use LogicException;

final class FakeProcessRunner implements ProcessRunner
{
    /**
     * @var list<array{executableKey:string, arguments:list<string>, environmentOverrides:array<string,string>}>
     */
    public array $calls = [];

    /**
     * @param list<ProcessResult> $results
     */
    public function __construct(private array $results) {}

    public function run(string $executableKey, array $arguments = [], array $environmentOverrides = []): ProcessResult
    {
        $this->calls[] = [
            'executableKey' => $executableKey,
            'arguments' => $arguments,
            'environmentOverrides' => $environmentOverrides,
        ];
        $result = array_shift($this->results);

        if (null === $result) {
            throw new LogicException('The fake process runner received an unexpected call.');
        }

        return $result;
    }
}
