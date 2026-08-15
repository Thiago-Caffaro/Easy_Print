<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Upload;

use function bin2hex;

use const DIRECTORY_SEPARATOR;

use EasyPrint\Application\Document\PdfUploadFailure;
use EasyPrint\Application\Document\PdfUploadResult;
use EasyPrint\Domain\Document\StoredPdf;

use function file_exists;

use const FILEINFO_MIME_TYPE;

use function filesize;

use finfo;

use function is_int;
use function is_string;
use function is_writable;
use function pathinfo;

use const PATHINFO_EXTENSION;

use function preg_match;

use Psr\Http\Message\UploadedFileInterface;

use function random_bytes;
use function realpath;
use function rtrim;
use function str_contains;
use function str_replace;
use function strtolower;

use Throwable;

use function unlink;

use const UPLOAD_ERR_FORM_SIZE;
use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_OK;

final readonly class SecurePdfUpload
{
    private const MEDIA_TYPE = 'application/pdf';

    public function __construct(
        private string $storageDirectory,
        private string $publicDirectory,
        private int $maximumBytes,
        private PdfStructureInspector $structureInspector,
    ) {}

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

        if (!is_string($originalName) || !$this->validOriginalName($originalName)) {
            return PdfUploadResult::rejected(PdfUploadFailure::InvalidName);
        }

        if ('pdf' !== strtolower(pathinfo($originalName, PATHINFO_EXTENSION))) {
            return PdfUploadResult::rejected(PdfUploadFailure::InvalidExtension);
        }

        $declaredSize = $upload->getSize();

        if (null !== $declaredSize && ($declaredSize < 1 || $declaredSize > $this->maximumBytes)) {
            return PdfUploadResult::rejected(PdfUploadFailure::TooLarge);
        }

        $storageRoot = $this->safeStorageRoot();

        if (null === $storageRoot) {
            return PdfUploadResult::rejected(PdfUploadFailure::StorageUnavailable);
        }

        $storedName = bin2hex(random_bytes(16)) . '.pdf';
        $target = $storageRoot . DIRECTORY_SEPARATOR . $storedName;

        if (file_exists($target)) {
            return PdfUploadResult::rejected(PdfUploadFailure::StorageUnavailable);
        }

        try {
            $upload->moveTo($target);
        } catch (Throwable) {
            return PdfUploadResult::rejected(PdfUploadFailure::StorageUnavailable);
        }

        $failure = $this->validateStoredFile($target, $storageRoot);

        if (null !== $failure) {
            @unlink($target);

            return PdfUploadResult::rejected($failure);
        }

        $byteSize = filesize($target);

        if (!is_int($byteSize)) {
            @unlink($target);

            return PdfUploadResult::rejected(PdfUploadFailure::StorageUnavailable);
        }

        return PdfUploadResult::accepted(new StoredPdf(
            storedName: $storedName,
            absolutePath: $target,
            originalName: $originalName,
            byteSize: $byteSize,
            mediaType: self::MEDIA_TYPE,
        ));
    }

    private function validOriginalName(string $name): bool
    {
        return '' !== $name
            && strlen($name) <= 255
            && !str_contains($name, '/')
            && !str_contains($name, '\\')
            && 1 !== preg_match('/[\x00-\x1F\x7F]/', $name);
    }

    private function safeStorageRoot(): ?string
    {
        $storageRoot = realpath($this->storageDirectory);
        $publicRoot = realpath($this->publicDirectory);

        if (false === $storageRoot || false === $publicRoot || !is_writable($storageRoot)) {
            return null;
        }

        $storage = $this->normalizedPath($storageRoot);
        $public = $this->normalizedPath($publicRoot);

        if ($storage === $public || str_starts_with($storage . '/', $public . '/')) {
            return null;
        }

        return $storageRoot;
    }

    private function validateStoredFile(string $target, string $storageRoot): ?PdfUploadFailure
    {
        $resolvedTarget = realpath($target);

        if (false === $resolvedTarget || !$this->isWithin($storageRoot, $resolvedTarget)) {
            return PdfUploadFailure::StorageUnavailable;
        }

        $byteSize = filesize($resolvedTarget);

        if (!is_int($byteSize) || $byteSize < 1 || $byteSize > $this->maximumBytes) {
            return PdfUploadFailure::TooLarge;
        }

        try {
            $mediaType = new finfo(FILEINFO_MIME_TYPE)->file($resolvedTarget);
        } catch (Throwable) {
            return PdfUploadFailure::StorageUnavailable;
        }

        if (self::MEDIA_TYPE !== $mediaType) {
            return PdfUploadFailure::MimeMismatch;
        }

        if (!$this->structureInspector->isValid($resolvedTarget, $byteSize)) {
            return PdfUploadFailure::InvalidPdf;
        }

        return null;
    }

    private function isWithin(string $root, string $path): bool
    {
        $root = $this->normalizedPath($root);
        $path = $this->normalizedPath($path);

        return str_starts_with($path, $root . '/');
    }

    private function normalizedPath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        return rtrim(PHP_OS_FAMILY === 'Windows' ? strtolower($normalized) : $normalized, '/');
    }
}
