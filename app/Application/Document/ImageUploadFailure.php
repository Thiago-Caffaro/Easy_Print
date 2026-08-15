<?php

declare(strict_types=1);

namespace EasyPrint\Application\Document;

enum ImageUploadFailure: string
{
    case DimensionsTooLarge = 'dimensions_too_large';
    case InvalidExtension = 'invalid_extension';
    case InvalidImage = 'invalid_image';
    case InvalidName = 'invalid_name';
    case MimeMismatch = 'mime_mismatch';
    case Missing = 'missing';
    case StorageUnavailable = 'storage_unavailable';
    case TooLarge = 'too_large';
    case UploadFailed = 'upload_failed';

    public function translationKey(): string
    {
        return 'image_upload.error.' . $this->value;
    }
}
