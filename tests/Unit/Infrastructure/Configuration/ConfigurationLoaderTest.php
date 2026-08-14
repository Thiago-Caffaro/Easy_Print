<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Unit\Infrastructure\Configuration;

use EasyPrint\Infrastructure\Configuration\ConfigurationException;
use EasyPrint\Infrastructure\Configuration\ConfigurationLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigurationLoaderTest extends TestCase
{
    public function testItLoadsTypedDefaultsForTheSupportedRuntime(): void
    {
        $config = ConfigurationLoader::load([], 'C:\\easy-print');

        self::assertSame('production', $config->environment);
        self::assertFalse($config->debug);
        self::assertSame('pt-BR', $config->defaultLocale);
        self::assertSame(['pt-BR', 'en'], $config->enabledLocales);
        self::assertSame('cups', $config->cupsHost);
        self::assertSame(631, $config->cupsPort);
        self::assertSame(26_214_400, $config->uploadMaxBytes);
        self::assertSame('/usr/bin/lp', $config->cupsExecutables['lp']);
    }

    /**
     * @param array<string,string> $environment
     */
    #[DataProvider('invalidConfigurationProvider')]
    public function testItRejectsMalformedAndOutOfBoundsValues(array $environment, string $setting): void
    {
        try {
            ConfigurationLoader::load($environment, 'C:\\easy-print');
            self::fail('Expected invalid configuration to be rejected.');
        } catch (ConfigurationException $exception) {
            self::assertStringContainsString($setting, $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{array<string,string>,string}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'port is outside the TCP range' => [['CUPS_PORT' => '70000'], 'CUPS_PORT'];
        yield 'host includes a scheme' => [['CUPS_HOST' => 'http://private.example'], 'CUPS_HOST'];
        yield 'upload limit is too small' => [['UPLOAD_MAX_BYTES' => '12'], 'UPLOAD_MAX_BYTES'];
        yield 'timeout is unbounded' => [['PROCESS_TIMEOUT_SECONDS' => '0'], 'PROCESS_TIMEOUT_SECONDS'];
        yield 'database path is relative' => [['DATABASE_PATH' => '../easy-print.sqlite'], 'DATABASE_PATH'];
        yield 'locale is unsupported' => [['APP_LOCALE' => 'fr'], 'APP_LOCALE'];
        yield 'default locale is not enabled' => [['APP_LOCALE' => 'en', 'APP_ENABLED_LOCALES' => 'pt-BR'], 'APP_LOCALE'];
        yield 'executable path is relative' => [['CUPS_LP_PATH' => 'lp'], 'CUPS_LP_PATH'];
    }

    public function testStartupErrorsDoNotRepeatTheInvalidPrivateValue(): void
    {
        $privateValue = 'https://user:password@private.example/internal';

        try {
            ConfigurationLoader::load(['CUPS_HOST' => $privateValue], 'C:\\easy-print');
            self::fail('Expected the private value to be rejected.');
        } catch (ConfigurationException $exception) {
            self::assertStringNotContainsString($privateValue, $exception->getMessage());
            self::assertStringContainsString('CUPS_HOST', $exception->getMessage());
        }
    }
}
