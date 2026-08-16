<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Cups;

use function array_values;
use function count;
use function preg_match;
use function preg_split;
use function rtrim;
use function strlen;
use function trim;

final class LpstatOutputParser
{
    public function schedulerIsRunning(string $output): bool
    {
        return match (trim($output)) {
            'scheduler is running' => true,
            'scheduler is not running' => false,
            default => throw new MalformedLpstatOutput('The scheduler response was not recognized.'),
        };
    }

    public function defaultQueueIdentifier(string $output): ?string
    {
        $output = trim($output);

        if ('no system default destination' === $output) {
            return null;
        }

        if (1 !== preg_match('/^system default destination: (.+)$/D', $output, $matches)) {
            throw new MalformedLpstatOutput('The default destination response was not recognized.');
        }

        return $this->queueIdentifier($matches[1]);
    }

    /**
     * @return list<string>
     */
    public function queueIdentifiers(string $output): array
    {
        if ('' === trim($output)) {
            return [];
        }

        $lines = preg_split('/\r\n|\n|\r/', rtrim($output, "\r\n"));

        if (false === $lines) {
            throw new MalformedLpstatOutput('The queue list could not be split into lines.');
        }

        $identifiers = array_values(array_map($this->queueIdentifier(...), $lines));

        if (count($identifiers) !== count(array_unique($identifiers))) {
            throw new MalformedLpstatOutput('The queue list contains duplicate identifiers.');
        }

        return $identifiers;
    }

    private function queueIdentifier(string $identifier): string
    {
        if ('' === $identifier || strlen($identifier) > 127 || 1 === preg_match('/[\x00-\x1F\x7F]/', $identifier)) {
            throw new MalformedLpstatOutput('A queue identifier was empty, too long, or contained control characters.');
        }

        return $identifier;
    }
}
