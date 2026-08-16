<?php

declare(strict_types=1);

namespace EasyPrint\Domain\Printer;

use function array_unique;
use function count;
use function in_array;

use InvalidArgumentException;

use function is_string;

final readonly class QueueSnapshot
{
    /**
     * @param list<string> $queueIdentifiers
     */
    public function __construct(
        public CupsConnectivity $connectivity,
        public array $queueIdentifiers = [],
        public ?string $defaultQueueIdentifier = null,
    ) {
        if (CupsConnectivity::Available !== $connectivity && ([] !== $queueIdentifiers || null !== $defaultQueueIdentifier)) {
            throw new InvalidArgumentException('Unavailable snapshots cannot contain queue data.');
        }

        foreach ($queueIdentifiers as $identifier) {
            if (!is_string($identifier) || '' === $identifier) {
                throw new InvalidArgumentException('Queue identifiers must be non-empty strings.');
            }
        }

        if (count($queueIdentifiers) !== count(array_unique($queueIdentifiers))) {
            throw new InvalidArgumentException('Queue identifiers must be unique.');
        }
    }

    public static function failed(CupsConnectivity $connectivity): self
    {
        if (CupsConnectivity::Available === $connectivity) {
            throw new InvalidArgumentException('An available snapshot is not a failure.');
        }

        return new self($connectivity);
    }

    public function contains(string $queueIdentifier): bool
    {
        return in_array($queueIdentifier, $this->queueIdentifiers, true);
    }
}
