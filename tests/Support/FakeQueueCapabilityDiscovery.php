<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Support;

use function array_shift;

use EasyPrint\Application\Printer\QueueCapabilityDiscovery;
use EasyPrint\Domain\Printer\CapabilitySnapshot;
use LogicException;

final class FakeQueueCapabilityDiscovery implements QueueCapabilityDiscovery
{
    /** @var list<string> */
    public array $calls = [];

    /**
     * @param list<CapabilitySnapshot> $snapshots
     */
    public function __construct(private array $snapshots) {}

    public function discover(string $queueIdentifier): CapabilitySnapshot
    {
        $this->calls[] = $queueIdentifier;
        $snapshot = array_shift($this->snapshots);

        if (null === $snapshot) {
            throw new LogicException('The fake capability discovery received an unexpected call.');
        }

        return $snapshot;
    }
}
