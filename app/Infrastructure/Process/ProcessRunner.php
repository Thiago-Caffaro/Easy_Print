<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Process;

interface ProcessRunner
{
    /**
     * @param list<string>         $arguments
     * @param array<string,string> $environmentOverrides
     */
    public function run(string $executableKey, array $arguments = [], array $environmentOverrides = []): ProcessResult;
}
