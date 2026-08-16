<?php

declare(strict_types=1);

namespace EasyPrint\Domain\Printer;

use function array_unique;
use function count;

use InvalidArgumentException;

final readonly class QueueSnapshot
{
    /**
     * @param list<PrinterQueue> $queues
     */
    public function __construct(
        public CupsConnectivity $connectivity,
        public array $queues = [],
        public ?string $defaultQueueIdentifier = null,
    ) {
        if (CupsConnectivity::Available !== $connectivity && ([] !== $queues || null !== $defaultQueueIdentifier)) {
            throw new InvalidArgumentException('Unavailable snapshots cannot contain queue data.');
        }

        $identifiers = $this->queueIdentifiers();

        if (count($identifiers) !== count(array_unique($identifiers))) {
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
        return null !== $this->queue($queueIdentifier);
    }

    public function queue(string $queueIdentifier): ?PrinterQueue
    {
        foreach ($this->queues as $queue) {
            if ($queueIdentifier === $queue->identifier) {
                return $queue;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function queueIdentifiers(): array
    {
        return array_map(static fn(PrinterQueue $queue): string => $queue->identifier, $this->queues);
    }
}
