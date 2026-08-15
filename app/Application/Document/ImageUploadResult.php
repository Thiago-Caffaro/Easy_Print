<?php

declare(strict_types=1);

namespace EasyPrint\Application\Document;

use EasyPrint\Domain\Document\StoredImage;
use InvalidArgumentException;

final readonly class ImageUploadResult
{
    private function __construct(
        public ?StoredImage $document,
        public ?ImageUploadFailure $failure,
    ) {
        if ((null === $document) === (null === $failure)) {
            throw new InvalidArgumentException('An upload result must contain exactly one outcome.');
        }
    }

    public static function accepted(StoredImage $document): self
    {
        return new self($document, null);
    }

    public static function rejected(ImageUploadFailure $failure): self
    {
        return new self(null, $failure);
    }

    public function succeeded(): bool
    {
        return null !== $this->document;
    }
}
