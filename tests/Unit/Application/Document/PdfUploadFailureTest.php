<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Unit\Application\Document;

use function array_key_exists;
use function dirname;

use EasyPrint\Application\Document\PdfUploadFailure;
use PHPUnit\Framework\TestCase;

final class PdfUploadFailureTest extends TestCase
{
    public function testEverySafeFailureHasPortugueseAndEnglishMessages(): void
    {
        $root = dirname(__DIR__, 4);
        $portuguese = require $root . '/resources/translations/pt-BR.php';
        $english = require $root . '/resources/translations/en.php';

        foreach (PdfUploadFailure::cases() as $failure) {
            self::assertTrue(array_key_exists($failure->translationKey(), $portuguese));
            self::assertTrue(array_key_exists($failure->translationKey(), $english));
        }
    }
}
