<?php

declare(strict_types=1);

namespace EasyPrint\Domain\Printer;

use function array_unique;
use function count;

use InvalidArgumentException;

use function preg_match;
use function strlen;

final readonly class CapabilityOption
{
    /**
     * @param list<CapabilityChoice> $choices
     */
    public function __construct(
        public string $technicalIdentifier,
        public string $driverLabel,
        public CapabilityCategory $category,
        public array $choices,
        public ?string $defaultTechnicalIdentifier,
    ) {
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,127}$/D', $technicalIdentifier)) {
            throw new InvalidArgumentException('A capability option identifier is invalid.');
        }

        if ('' === $driverLabel
            || strlen($driverLabel) > 256
            || 1 !== preg_match('//u', $driverLabel)
            || 1 === preg_match('/[\x00-\x1F\x7F]/', $driverLabel)) {
            throw new InvalidArgumentException('A capability driver label is invalid.');
        }

        if ([] === $choices || count($choices) > 256) {
            throw new InvalidArgumentException('A capability option must contain a bounded choice list.');
        }

        $identifiers = array_map(
            static fn(CapabilityChoice $choice): string => $choice->technicalIdentifier,
            $choices,
        );

        if (count($identifiers) !== count(array_unique($identifiers))) {
            throw new InvalidArgumentException('Capability choice identifiers must be unique.');
        }

        if (null !== $defaultTechnicalIdentifier && !in_array($defaultTechnicalIdentifier, $identifiers, true)) {
            throw new InvalidArgumentException('The capability default must be one of its choices.');
        }
    }

    public function isRenderable(): bool
    {
        return CapabilityCategory::Unknown !== $this->category;
    }
}
