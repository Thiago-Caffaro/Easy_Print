<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Upload;

use function fclose;
use function fopen;
use function fread;
use function fseek;
use function is_int;
use function max;
use function preg_match;
use function strlen;

final class PdfStructureInspector
{
    private const TRAILER_WINDOW_BYTES = 65_536;

    public function isValid(string $path, int $byteSize): bool
    {
        if ($byteSize < 32) {
            return false;
        }

        $stream = @fopen($path, 'rb');

        if (false === $stream) {
            return false;
        }

        try {
            $header = fread($stream, 8);

            if (false === $header || 1 !== preg_match('/^%PDF-(?:1\.[0-7]|2\.0)/D', $header)) {
                return false;
            }

            $windowStart = max(0, $byteSize - self::TRAILER_WINDOW_BYTES);

            if (0 !== fseek($stream, $windowStart)) {
                return false;
            }

            $trailerLength = $byteSize - $windowStart;

            if ($trailerLength < 1) {
                return false;
            }

            $trailer = fread($stream, $trailerLength);

            if (false === $trailer || 1 !== preg_match('/startxref\s+(\d+)\s+%%EOF\s*$/sD', $trailer, $matches)) {
                return false;
            }

            $xrefOffset = filter_var($matches[1], FILTER_VALIDATE_INT);

            if (!is_int($xrefOffset) || $xrefOffset < strlen($header) || $xrefOffset >= $byteSize) {
                return false;
            }

            if (0 !== fseek($stream, $xrefOffset)) {
                return false;
            }

            $xrefStart = fread($stream, 128);

            return false !== $xrefStart
                && 1 === preg_match('/^(?:xref\b|\d+\s+\d+\s+obj\b)/D', $xrefStart);
        } finally {
            fclose($stream);
        }
    }
}
