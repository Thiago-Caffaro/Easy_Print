<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Upload;

use EasyPrint\Application\Document\PdfUploadFailure;
use EasyPrint\Application\Document\PdfUploadResult;
use EasyPrint\Domain\Document\StoredPdf;

use const FILEINFO_MIME_TYPE;

use finfo;

use function is_string;
use function pathinfo;

use const PATHINFO_EXTENSION;

use Psr\Http\Message\UploadedFileInterface;

use function strtolower;

use Throwable;

use const UPLOAD_ERR_FORM_SIZE;
use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_OK;

final readonly class SecurePdfUpload
{
    private const MEDIA_TYPE = 'application/pdf';

    private PrivateUploadStorage $storage;

    public function __construct(
        string $storageDirectory,
        string $publicDirectory,
        private int $maximumBytes,
        private PdfStructureInspector $structureInspector,
    ) {
        $this->storage = new PrivateUploadStorage($storageDirectory, $publicDirectory);
    }

    public function store(UploadedFileInterface $upload): PdfUploadResult
    {
        $error = $upload->getError();

        if (UPLOAD_ERR_NO_FILE === $error) {
            return PdfUploadResult::rejected(PdfUploadFailure::Missing);
        }

        if (UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error) {
            return PdfUploadResult::rejected(PdfUploadFailure::TooLarge);
        }

        if (UPLOAD_ERR_OK !== $error) {
            return PdfUploadResult::rejected(PdfUploadFailure::UploadFailed);
        }

        $originalName = $upload->getClientFilename();

        if (!is_string($originalName) || !UploadNameValidator::isValid($originalName)) {
            return PdfUploadResult::rejected(PdfUploadFailure::InvalidName);
        }

        if ('pdf' !== strtolower(pathinfo($originalName, PATHINFO_EXTENSION))) {
            return PdfUploadResult::rejected(PdfUploadFailure::InvalidExtension);
        }

        $declaredSize = $upload->getSize();

        if (null !== $declaredSize && ($declaredSize < 1 || $declaredSize > $this->maximumBytes)) {
            return PdfUploadResult::rejected(PdfUploadFailure::TooLarge);
        }

        $stored = $this->storage->move($upload, 'pdf');

        if (null === $stored) {
            return PdfUploadResult::rejected(PdfUploadFailure::StorageUnavailable);
        }

        $failure = $this->validateStoredFile($stored);

        if (null !== $failure) {
            $this->storage->delete($stored);

            return PdfUploadResult::rejected($failure);
        }

        return PdfUploadResult::accepted(new StoredPdf(
            storedName: $stored->storedName,
            absolutePath: $stored->absolutePath,
            originalName: $originalName,
            byteSize: $stored->byteSize,
            mediaType: self::MEDIA_TYPE,
        ));
    }

    private function validateStoredFile(PrivateStoredUpload $stored): ?PdfUploadFailure
    {
        if ($stored->byteSize < 1 || $stored->byteSize > $this->maximumBytes) {
            return PdfUploadFailure::TooLarge;
        }

        try {
            $mediaType = new finfo(FILEINFO_MIME_TYPE)->file($stored->absolutePath);
        } catch (Throwable) {
            return PdfUploadFailure::StorageUnavailable;
        }

        if (self::MEDIA_TYPE !== $mediaType) {
            return PdfUploadFailure::MimeMismatch;
        }

        if (!$this->structureInspector->isValid($stored->absolutePath, $stored->byteSize)) {
            return PdfUploadFailure::InvalidPdf;
        }

        return null;
    }
}
