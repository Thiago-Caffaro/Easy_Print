<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Cups;

use const FILTER_VALIDATE_INT;

use function filter_var;
use function preg_match;
use function preg_quote;

final class LpSubmissionOutputParser
{
    public function cupsJobId(string $output, string $queueIdentifier): ?int
    {
        $pattern = '/\Arequest id is ' . preg_quote($queueIdentifier, '/') . '-(?<job>[1-9][0-9]*) \(1 file\(s\)\)\r?\n?\z/D';

        if (1 !== preg_match($pattern, $output, $matches)) {
            return null;
        }

        $jobId = filter_var($matches['job'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return false === $jobId ? null : $jobId;
    }
}
