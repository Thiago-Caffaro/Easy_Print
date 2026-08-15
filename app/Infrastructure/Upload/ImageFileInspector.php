<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Upload;

use EasyPrint\Domain\Document\ImageDimensions;

use function fclose;
use function fopen;
use function fread;
use function fseek;

use GdImage;

use function getimagesize;
use function imagecreatefromjpeg;
use function imagecreatefrompng;
use function imagesx;
use function imagesy;

use const SEEK_END;

final class ImageFileInspector
{
    private const JPEG_MEDIA_TYPE = 'image/jpeg';
    private const PNG_END = "\x00\x00\x00\x00IEND\xAE\x42\x60\x82";
    private const PNG_MEDIA_TYPE = 'image/png';

    public function dimensions(string $path, string $expectedMediaType): ?ImageDimensions
    {
        $details = @getimagesize($path);

        if (false === $details) {
            return null;
        }

        $width = $details[0];
        $height = $details[1];
        $type = $details[2];
        $expectedType = match ($expectedMediaType) {
            self::JPEG_MEDIA_TYPE => IMAGETYPE_JPEG,
            self::PNG_MEDIA_TYPE => IMAGETYPE_PNG,
            default => null,
        };

        if ($width < 1 || $height < 1 || $type !== $expectedType) {
            return null;
        }

        return new ImageDimensions($width, $height);
    }

    public function hasExpectedTerminator(string $path, int $byteSize, string $mediaType): bool
    {
        $expected = match ($mediaType) {
            self::JPEG_MEDIA_TYPE => "\xFF\xD9",
            self::PNG_MEDIA_TYPE => self::PNG_END,
            default => null,
        };

        if (null === $expected || $byteSize < strlen($expected)) {
            return false;
        }

        $stream = @fopen($path, 'rb');

        if (false === $stream) {
            return false;
        }

        try {
            if (0 !== fseek($stream, -strlen($expected), SEEK_END)) {
                return false;
            }

            return $expected === fread($stream, strlen($expected));
        } finally {
            fclose($stream);
        }
    }

    public function isDecodable(string $path, string $mediaType, ImageDimensions $expectedDimensions): bool
    {
        $image = match ($mediaType) {
            self::JPEG_MEDIA_TYPE => @imagecreatefromjpeg($path),
            self::PNG_MEDIA_TYPE => @imagecreatefrompng($path),
            default => false,
        };

        if (!$image instanceof GdImage) {
            return false;
        }

        return imagesx($image) === $expectedDimensions->width
            && imagesy($image) === $expectedDimensions->height;
    }
}
