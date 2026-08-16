<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

use EasyPrint\Domain\Document\StoredImage;
use EasyPrint\Domain\Document\StoredPdf;

final readonly class PrintDocument
{
    private function __construct(
        public string $absolutePath,
        public string $originalName,
        public int $byteSize,
        public string $mediaType,
    ) {}

    public static function fromStoredPdf(StoredPdf $document): self
    {
        return new self(
            $document->absolutePath,
            $document->originalName,
            $document->byteSize,
            $document->mediaType,
        );
    }

    public static function fromStoredImage(StoredImage $document): self
    {
        return new self(
            $document->absolutePath,
            $document->originalName,
            $document->byteSize,
            $document->mediaType,
        );
    }
}
