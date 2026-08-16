<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

use EasyPrint\Domain\Printer\PrinterQueue;
use EasyPrint\Domain\Printer\QueueSnapshot;

final class QueueSelectionResolver
{
    public function resolve(QueueSnapshot $snapshot, ?string $requested, ?string $persisted): QueueSelection
    {
        $requestedQueue = $this->queue($snapshot, $requested);

        if (null !== $requestedQueue) {
            return new QueueSelection($requestedQueue, SelectionSource::Requested, SelectionPersistence::Store);
        }

        $persistedQueue = $this->queue($snapshot, $persisted);

        if (null !== $persistedQueue) {
            return new QueueSelection($persistedQueue, SelectionSource::Persisted, SelectionPersistence::Keep);
        }

        $defaultQueue = $this->queue($snapshot, $snapshot->defaultQueueIdentifier);

        if (null !== $defaultQueue) {
            return new QueueSelection($defaultQueue, SelectionSource::DefaultQueue, SelectionPersistence::Store);
        }

        if ([] !== $snapshot->queues) {
            return new QueueSelection($snapshot->queues[0], SelectionSource::FirstAvailable, SelectionPersistence::Store);
        }

        return new QueueSelection(
            queue: null,
            source: SelectionSource::None,
            persistence: null === $persisted ? SelectionPersistence::Keep : SelectionPersistence::Clear,
        );
    }

    private function queue(QueueSnapshot $snapshot, ?string $identifier): ?PrinterQueue
    {
        return null === $identifier || '' === $identifier ? null : $snapshot->queue($identifier);
    }
}
