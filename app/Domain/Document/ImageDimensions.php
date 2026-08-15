<?php

declare(strict_types=1);

namespace EasyPrint\Domain\Document;

final readonly class ImageDimensions
{
    public function __construct(
        public int $width,
        public int $height,
    ) {}
}
