<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Upload;

use EasyPrint\Application\Document\ImageUploadFailure;
use EasyPrint\Application\Document\ImageUploadResult;
use EasyPrint\Domain\Document\ImageDimensions;
use EasyPrint\Domain\Document\StoredImage;

use const FILEINFO_MIME_TYPE;

use finfo;

use function intdiv;
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

final readonly class SecureImageUpload
{
    /**
     * @var array<string,array{mediaType:string,storedExtension:string}>
     */
    private const FORMATS = [
        'jpeg' => ['mediaType' => 'image/jpeg', 'storedExtension' => 'jpg'],
        'jpg' => ['mediaType' => 'image/jpeg', 'storedExtension' => 'jpg'],
        'png' => ['mediaType' => 'image/png', 'storedExtension' => 'png'],
    ];

    private PrivateUploadStorage $storage;

    public function __construct(
        string $storageDirectory,
        string $publicDirectory,
        private int $maximumBytes,
        private int $maximumWidth,
        private int $maximumHeight,
        private int $maximumPixels,
        private ImageFileInspector $inspector,
    ) {
        $this->storage = new PrivateUploadStorage($storageDirectory, $publicDirectory);
    }

    public function store(UploadedFileInterface $upload): ImageUploadResult
    {
        $error = $upload->getError();

        if (UPLOAD_ERR_NO_FILE === $error) {
            return ImageUploadResult::rejected(ImageUploadFailure::Missing);
        }

        if (UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error) {
            return ImageUploadResult::rejected(ImageUploadFailure::TooLarge);
        }

        if (UPLOAD_ERR_OK !== $error) {
            return ImageUploadResult::rejected(ImageUploadFailure::UploadFailed);
        }

        $originalName = $upload->getClientFilename();

        if (!is_string($originalName) || !UploadNameValidator::isValid($originalName)) {
            return ImageUploadResult::rejected(ImageUploadFailure::InvalidName);
        }

        $format = self::FORMATS[strtolower(pathinfo($originalName, PATHINFO_EXTENSION))] ?? null;

        if (null === $format) {
            return ImageUploadResult::rejected(ImageUploadFailure::InvalidExtension);
        }

        $declaredSize = $upload->getSize();

        if (null !== $declaredSize && ($declaredSize < 1 || $declaredSize > $this->maximumBytes)) {
            return ImageUploadResult::rejected(ImageUploadFailure::TooLarge);
        }

        $stored = $this->storage->move($upload, $format['storedExtension']);

        if (null === $stored) {
            return ImageUploadResult::rejected(ImageUploadFailure::StorageUnavailable);
        }

        $failure = $this->validateStoredFile($stored, $format['mediaType']);

        if ($failure instanceof ImageUploadFailure) {
            $this->storage->delete($stored);

            return ImageUploadResult::rejected($failure);
        }

        return ImageUploadResult::accepted(new StoredImage(
            storedName: $stored->storedName,
            absolutePath: $stored->absolutePath,
            originalName: $originalName,
            byteSize: $stored->byteSize,
            mediaType: $format['mediaType'],
            pixelWidth: $failure->width,
            pixelHeight: $failure->height,
        ));
    }

    private function validateStoredFile(
        PrivateStoredUpload $stored,
        string $expectedMediaType,
    ): ImageUploadFailure|ImageDimensions {
        if ($stored->byteSize < 1 || $stored->byteSize > $this->maximumBytes) {
            return ImageUploadFailure::TooLarge;
        }

        try {
            $detectedMediaType = new finfo(FILEINFO_MIME_TYPE)->file($stored->absolutePath);
        } catch (Throwable) {
            return ImageUploadFailure::StorageUnavailable;
        }

        if ($expectedMediaType !== $detectedMediaType) {
            return ImageUploadFailure::MimeMismatch;
        }

        $dimensions = $this->inspector->dimensions($stored->absolutePath, $expectedMediaType);

        if (null === $dimensions) {
            return ImageUploadFailure::InvalidImage;
        }

        if ($dimensions->width > $this->maximumWidth
            || $dimensions->height > $this->maximumHeight
            || $dimensions->width > intdiv($this->maximumPixels, $dimensions->height)
        ) {
            return ImageUploadFailure::DimensionsTooLarge;
        }

        if (!$this->inspector->hasExpectedTerminator($stored->absolutePath, $stored->byteSize, $expectedMediaType)
            || !$this->inspector->isDecodable($stored->absolutePath, $expectedMediaType, $dimensions)
        ) {
            return ImageUploadFailure::InvalidImage;
        }

        return $dimensions;
    }
}
