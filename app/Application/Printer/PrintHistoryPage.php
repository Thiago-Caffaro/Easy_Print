<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

use InvalidArgumentException;

final readonly class PrintHistoryPage
{
    /**
     * @param list<PrintHistoryEntry> $entries
     */
    public function __construct(
        public array $entries,
        public int $page,
        public int $perPage,
        public int $totalItems,
        public bool $available = true,
    ) {
        if ($page < 1 || $perPage < 1 || $totalItems < 0) {
            throw new InvalidArgumentException('History pagination values must be positive.');
        }
    }

    public function totalPages(): int
    {
        return max(1, (int) ceil($this->totalItems / $this->perPage));
    }

    public static function unavailable(int $page, int $perPage): self
    {
        return new self([], $page, $perPage, 0, false);
    }
}
