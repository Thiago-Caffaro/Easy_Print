<?php

declare(strict_types=1);

namespace EasyPrint\Application\Document;

enum PdfUploadFailure: string
{
    case InvalidExtension = 'invalid_extension';
    case InvalidName = 'invalid_name';
    case InvalidPdf = 'invalid_pdf';
    case MimeMismatch = 'mime_mismatch';
    case Missing = 'missing';
    case StorageUnavailable = 'storage_unavailable';
    case TooLarge = 'too_large';
    case UploadFailed = 'upload_failed';

    public function translationKey(): string
    {
        return 'upload.error.' . $this->value;
    }
}
