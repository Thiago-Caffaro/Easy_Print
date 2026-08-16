<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

use Closure;
use EasyPrint\Domain\Printer\CapabilitySnapshot;
use EasyPrint\Domain\Printer\CupsConnectivity;
use InvalidArgumentException;
use LogicException;

use function time;

final readonly class CachedQueueCapabilityDiscovery implements QueueCapabilityDiscovery
{
    /** @var Closure(): int */
    private Closure $clock;

    /**
     * @param null|Closure(): int $clock
     */
    public function __construct(
        private QueueCapabilityDiscovery $source,
        private CapabilitySnapshotCache $cache,
        private string $serverKey,
        private int $ttlSeconds,
        ?Closure $clock = null,
    ) {
        if ($ttlSeconds < 0) {
            throw new InvalidArgumentException('The capability cache TTL cannot be negative.');
        }

        $this->clock = $clock ?? static fn(): int => time();
    }

    public function discover(string $queueIdentifier): CapabilitySnapshot
    {
        $now = ($this->clock)();

        if ($this->ttlSeconds > 0) {
            $cached = $this->cache->find($this->serverKey, $queueIdentifier, $now);

            if (null !== $cached) {
                return $cached;
            }
        }

        $snapshot = $this->source->discover($queueIdentifier);

        if ($snapshot->queueIdentifier !== $queueIdentifier) {
            throw new LogicException('Capability discovery returned a snapshot for a different queue.');
        }

        if (CupsConnectivity::Available === $snapshot->connectivity && $this->ttlSeconds > 0) {
            $this->cache->save(
                $this->serverKey,
                $snapshot,
                $now,
                $now + $this->ttlSeconds,
            );
        } else {
            $this->cache->invalidate($this->serverKey, $queueIdentifier);
        }

        return $snapshot;
    }
}
