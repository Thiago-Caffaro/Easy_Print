<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Upload;

final readonly class PrivateStoredUpload
{
    public function __construct(
        public string $storedName,
        public string $absolutePath,
        public int $byteSize,
    ) {}
}
