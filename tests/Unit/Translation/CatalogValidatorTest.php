<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Unit\Translation;

use function dirname;

use EasyPrint\Translation\CatalogValidator;
use EasyPrint\Translation\Translator;
use PHPUnit\Framework\TestCase;

final class CatalogValidatorTest extends TestCase
{
    public function testPortugueseAndEnglishCatalogsContainTheSameKeys(): void
    {
        $directory = dirname(__DIR__, 3) . '/resources/translations';
        $portuguese = require $directory . '/pt-BR.php';
        $english = require $directory . '/en.php';

        self::assertSame(['missing' => [], 'orphaned' => []], CatalogValidator::compare($portuguese, $english));
    }

    public function testTheTranslatorUsesThePortugueseFallbackForAnUnknownLocale(): void
    {
        $directory = dirname(__DIR__, 3) . '/resources/translations';
        $translator = Translator::fromDirectory($directory, ['pt-BR', 'en'], 'pt-BR');

        self::assertSame('Configuração válida', $translator->translate('unsupported', 'home.status_ready'));
    }
}
