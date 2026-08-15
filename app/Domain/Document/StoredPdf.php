<?php

declare(strict_types=1);

namespace EasyPrint\Domain\Document;

final readonly class StoredPdf
{
    public function __construct(
        public string $storedName,
        public string $absolutePath,
        public string $originalName,
        public int $byteSize,
        public string $mediaType,
    ) {}
}
