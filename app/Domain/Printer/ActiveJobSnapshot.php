<?php

declare(strict_types=1);

namespace EasyPrint\Domain\Printer;

use function count;

use InvalidArgumentException;

final readonly class ActiveJobSnapshot
{
    /**
     * @param list<ActivePrintJob> $jobs
     */
    public function __construct(
        public CupsConnectivity $connectivity,
        public array $jobs = [],
    ) {
        if (CupsConnectivity::Available !== $connectivity && [] !== $jobs) {
            throw new InvalidArgumentException('An unavailable snapshot cannot contain active jobs.');
        }

        if (count($jobs) > 250) {
            throw new InvalidArgumentException('An active job snapshot exceeds the supported display limit.');
        }

        $identifiers = array_map(
            static fn(ActivePrintJob $job): string => $job->requestIdentifier(),
            $jobs,
        );

        if (count($identifiers) !== count(array_unique($identifiers))) {
            throw new InvalidArgumentException('Active job identifiers must be unique.');
        }
    }

    public static function failed(CupsConnectivity $connectivity): self
    {
        if (CupsConnectivity::Available === $connectivity) {
            throw new InvalidArgumentException('An available active job snapshot is not a failure.');
        }

        return new self($connectivity);
    }
}
