<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

use EasyPrint\Domain\Printer\PrinterQueue;
use InvalidArgumentException;

final readonly class QueueSelection
{
    public function __construct(
        public ?PrinterQueue $queue,
        public SelectionSource $source,
        public SelectionPersistence $persistence,
    ) {
        if (SelectionPersistence::Store === $persistence && null === $queue) {
            throw new InvalidArgumentException('A stored selection must contain a current queue.');
        }

        if (SelectionPersistence::Clear === $persistence && null !== $queue) {
            throw new InvalidArgumentException('A cleared selection cannot contain a queue.');
        }
    }
}
