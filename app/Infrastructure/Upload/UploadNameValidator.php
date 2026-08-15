<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Upload;

use function preg_match;
use function str_contains;

final class UploadNameValidator
{
    public static function isValid(string $name): bool
    {
        return '' !== $name
            && strlen($name) <= 255
            && !str_contains($name, '/')
            && !str_contains($name, '\\')
            && 1 !== preg_match('/[\x00-\x1F\x7F]/', $name);
    }
}
