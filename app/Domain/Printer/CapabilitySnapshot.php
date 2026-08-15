<?php

declare(strict_types=1);

namespace EasyPrint\Domain\Printer;

use function array_unique;
use function count;

use InvalidArgumentException;

use function preg_match;

final readonly class CapabilitySnapshot
{
    /**
     * @param list<CapabilityOption> $options
     */
    public function __construct(
        public string $queueIdentifier,
        public CupsConnectivity $connectivity,
        public array $options = [],
        public ?string $fingerprint = null,
    ) {
        if ('' === $queueIdentifier) {
            throw new InvalidArgumentException('A capability snapshot requires a queue identifier.');
        }

        if (count($options) > 128) {
            throw new InvalidArgumentException('A capability snapshot contains too many options.');
        }

        if (CupsConnectivity::Available !== $connectivity && ([] !== $options || null !== $fingerprint)) {
            throw new InvalidArgumentException('An unavailable capability snapshot cannot contain capability data.');
        }

        if (CupsConnectivity::Available === $connectivity
            && (null === $fingerprint || 1 !== preg_match('/^[a-f0-9]{64}$/D', $fingerprint))) {
            throw new InvalidArgumentException('An available capability snapshot requires a SHA-256 fingerprint.');
        }

        $identifiers = array_map(
            static fn(CapabilityOption $option): string => $option->technicalIdentifier,
            $options,
        );

        if (count($identifiers) !== count(array_unique($identifiers))) {
            throw new InvalidArgumentException('Capability option identifiers must be unique.');
        }
    }

    public static function failed(string $queueIdentifier, CupsConnectivity $connectivity): self
    {
        if (CupsConnectivity::Available === $connectivity) {
            throw new InvalidArgumentException('An available capability snapshot is not a failure.');
        }

        return new self($queueIdentifier, $connectivity);
    }

    /**
     * @return list<CapabilityOption>
     */
    public function renderableOptions(): array
    {
        return array_values(array_filter(
            $this->options,
            static fn(CapabilityOption $option): bool => $option->isRenderable(),
        ));
    }

    /**
     * @return list<CapabilityOption>
     */
    public function unknownOptions(): array
    {
        return array_values(array_filter(
            $this->options,
            static fn(CapabilityOption $option): bool => !$option->isRenderable(),
        ));
    }
}
