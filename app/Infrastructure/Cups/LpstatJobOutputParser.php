<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Cups;

use EasyPrint\Domain\Printer\ActiveJobState;
use EasyPrint\Domain\Printer\ActivePrintJob;

use const FILTER_VALIDATE_INT;

use function filter_var;
use function preg_match;
use function preg_split;
use function rtrim;
use function strlen;
use function trim;

final class LpstatJobOutputParser
{
    /**
     * @return list<ActivePrintJob>
     */
    public function activeJobs(string $output): array
    {
        if ('' === trim($output)) {
            return [];
        }

        $lines = preg_split('/\r\n|\n|\r/', rtrim($output, "\r\n"));

        if (false === $lines || count($lines) > 250) {
            throw new MalformedLpstatOutput('The active job list is malformed or exceeds the display limit.');
        }

        $jobs = [];

        foreach ($lines as $line) {
            if (1 !== preg_match(
                '/\A(?<request>\S+)\s+(?<owner>\S(?:.*?\S)?)\s+(?<bytes>[0-9]+)\s{3}(?<created>[A-Za-z0-9:+\- ]{1,80})\z/D',
                $line,
                $matches,
            )) {
                throw new MalformedLpstatOutput('An active job row was not recognized.');
            }

            if (1 !== preg_match('/\A(?<queue>[^\s\/#]{1,127})-(?<job>[1-9][0-9]*)\z/D', $matches['request'], $request)) {
                throw new MalformedLpstatOutput('An active job request identifier was not recognized.');
            }

            $cupsJobId = $this->positiveInteger($request['job']);
            $byteSize = $this->nonNegativeInteger($matches['bytes']);
            $submittedAt = trim($matches['created']);

            if (null === $cupsJobId || null === $byteSize || '' === $submittedAt || strlen($submittedAt) > 80) {
                throw new MalformedLpstatOutput('An active job row contains invalid bounded values.');
            }

            $jobs[] = new ActivePrintJob(
                queueIdentifier: $request['queue'],
                cupsJobId: $cupsJobId,
                byteSize: $byteSize,
                submittedAtLabel: $submittedAt,
                state: ActiveJobState::Unknown,
            );
        }

        $identifiers = array_map(
            static fn(ActivePrintJob $job): string => $job->requestIdentifier(),
            $jobs,
        );

        if (count($identifiers) !== count(array_unique($identifiers))) {
            throw new MalformedLpstatOutput('The active job list contains duplicate request identifiers.');
        }

        return $jobs;
    }

    private function positiveInteger(string $value): ?int
    {
        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return false === $validated ? null : $validated;
    }

    private function nonNegativeInteger(string $value): ?int
    {
        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        return false === $validated ? null : $validated;
    }
}
