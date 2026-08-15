<?php

declare(strict_types=1);

namespace EasyPrint\Application\Document;

use EasyPrint\Domain\Document\StoredPdf;
use InvalidArgumentException;

final readonly class PdfUploadResult
{
    private function __construct(
        public ?StoredPdf $document,
        public ?PdfUploadFailure $failure,
    ) {
        if ((null === $document) === (null === $failure)) {
            throw new InvalidArgumentException('An upload result must contain exactly one outcome.');
        }
    }

    public static function accepted(StoredPdf $document): self
    {
        return new self($document, null);
    }

    public static function rejected(PdfUploadFailure $failure): self
    {
        return new self(null, $failure);
    }

    public function succeeded(): bool
    {
        return null !== $this->document;
    }
}
