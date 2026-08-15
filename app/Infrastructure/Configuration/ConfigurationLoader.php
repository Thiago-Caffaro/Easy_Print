<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Configuration;

use function array_key_exists;
use function array_unique;
use function dirname;
use function explode;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOLEAN;
use const FILTER_VALIDATE_INT;
use const FILTER_VALIDATE_IP;

use function filter_var;
use function getenv;
use function in_array;
use function is_string;
use function preg_match;
use function rtrim;
use function sprintf;
use function str_contains;
use function strlen;
use function trim;

final class ConfigurationLoader
{
    private const SUPPORTED_LOCALES = ['pt-BR', 'en'];

    /**
     * @param array<string, scalar|null>|null $source
     */
    public static function load(?array $source = null, ?string $projectRoot = null): AppConfig
    {
        $root = rtrim($projectRoot ?? dirname(__DIR__, 3), '/\\');
        $read = static function (string $name, string $default) use ($source): string {
            if (null !== $source && array_key_exists($name, $source)) {
                $value = $source[$name];

                if (!is_string($value) && null !== $value) {
                    return (string) $value;
                }

                return $value ?? $default;
            }

            $value = getenv($name);

            return false === $value ? $default : $value;
        };

        $defaultLocale = self::locale('APP_LOCALE', $read('APP_LOCALE', 'pt-BR'));
        $enabledLocales = self::locales($read('APP_ENABLED_LOCALES', 'pt-BR,en'));

        if (!in_array($defaultLocale, $enabledLocales, true)) {
            throw new ConfigurationException('APP_LOCALE must be included in APP_ENABLED_LOCALES.');
        }

        return new AppConfig(
            environment: self::environment($read('APP_ENV', 'production')),
            debug: self::boolean('APP_DEBUG', $read('APP_DEBUG', 'false')),
            basePath: self::basePath($read('APP_BASE_PATH', '')),
            defaultLocale: $defaultLocale,
            enabledLocales: $enabledLocales,
            cupsHost: self::host($read('CUPS_HOST', 'cups')),
            cupsPort: self::integer('CUPS_PORT', $read('CUPS_PORT', '631'), 1, 65_535),
            cupsEncryption: self::choice('CUPS_ENCRYPTION', $read('CUPS_ENCRYPTION', 'never'), ['never', 'required']),
            cupsServerKey: self::serverKey($read('CUPS_SERVER_KEY', 'primary')),
            databasePath: self::absolutePath('DATABASE_PATH', $read('DATABASE_PATH', $root . '/storage/database/easy-print.sqlite')),
            temporaryPath: self::absolutePath('TEMPORARY_PATH', $read('TEMPORARY_PATH', $root . '/storage/temporary')),
            uploadMaxBytes: self::integer('UPLOAD_MAX_BYTES', $read('UPLOAD_MAX_BYTES', '26214400'), 1_024, 104_857_600),
            imageMaxWidth: self::integer('IMAGE_MAX_WIDTH', $read('IMAGE_MAX_WIDTH', '16384'), 1, 100_000),
            imageMaxHeight: self::integer('IMAGE_MAX_HEIGHT', $read('IMAGE_MAX_HEIGHT', '16384'), 1, 100_000),
            imageMaxPixels: self::integer('IMAGE_MAX_PIXELS', $read('IMAGE_MAX_PIXELS', '50000000'), 1, 250_000_000),
            temporaryFileTtlSeconds: self::integer('TEMP_FILE_TTL_SECONDS', $read('TEMP_FILE_TTL_SECONDS', '3600'), 60, 86_400),
            historyRetentionDays: self::integer('HISTORY_RETENTION_DAYS', $read('HISTORY_RETENTION_DAYS', '90'), 1, 3_650),
            errorRetentionDays: self::integer('ERROR_RETENTION_DAYS', $read('ERROR_RETENTION_DAYS', '30'), 1, 365),
            capabilityCacheTtlSeconds: self::integer('CAPABILITY_CACHE_TTL_SECONDS', $read('CAPABILITY_CACHE_TTL_SECONDS', '60'), 0, 3_600),
            processTimeoutSeconds: self::integer('PROCESS_TIMEOUT_SECONDS', $read('PROCESS_TIMEOUT_SECONDS', '15'), 1, 120),
            processOutputMaxBytes: self::integer('PROCESS_OUTPUT_MAX_BYTES', $read('PROCESS_OUTPUT_MAX_BYTES', '262144'), 1_024, 1_048_576),
            cupsExecutables: [
                'lp' => self::absolutePath('CUPS_LP_PATH', $read('CUPS_LP_PATH', '/usr/bin/lp')),
                'lpstat' => self::absolutePath('CUPS_LPSTAT_PATH', $read('CUPS_LPSTAT_PATH', '/usr/bin/lpstat')),
                'lpoptions' => self::absolutePath('CUPS_LPOPTIONS_PATH', $read('CUPS_LPOPTIONS_PATH', '/usr/bin/lpoptions')),
                'cancel' => self::absolutePath('CUPS_CANCEL_PATH', $read('CUPS_CANCEL_PATH', '/usr/bin/cancel')),
            ],
        );
    }

    private static function environment(string $value): string
    {
        return self::choice('APP_ENV', trim($value), ['production', 'development', 'testing']);
    }

    private static function boolean(string $name, string $value): bool
    {
        $validated = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if (null === $validated) {
            throw new ConfigurationException(sprintf('%s must be a boolean value.', $name));
        }

        return $validated;
    }

    private static function integer(string $name, string $value, int $minimum, int $maximum): int
    {
        $validated = filter_var($value, FILTER_VALIDATE_INT);

        if (false === $validated || $validated < $minimum || $validated > $maximum) {
            throw new ConfigurationException(sprintf('%s must be an integer between %d and %d.', $name, $minimum, $maximum));
        }

        return $validated;
    }

    /**
     * @param list<string> $choices
     */
    private static function choice(string $name, string $value, array $choices): string
    {
        if (!in_array($value, $choices, true)) {
            throw new ConfigurationException(sprintf('%s contains an unsupported value.', $name));
        }

        return $value;
    }

    private static function basePath(string $value): string
    {
        $value = rtrim(trim($value), '/');

        if ('' !== $value && (
            !str_starts_with($value, '/')
            || str_contains($value, '..')
            || 1 !== preg_match('/^\/[A-Za-z0-9._~\/-]*$/D', $value)
        )) {
            throw new ConfigurationException('APP_BASE_PATH must be empty or an absolute URL path without traversal segments.');
        }

        return $value;
    }

    private static function host(string $value): string
    {
        $value = trim($value);

        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }

        if (strlen($value) > 253 || 1 !== preg_match('/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)(?:\.(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?))*$/D', $value)) {
            throw new ConfigurationException('CUPS_HOST must be a valid IP address or DNS hostname without a scheme or port.');
        }

        return $value;
    }

    private static function serverKey(string $value): string
    {
        $value = trim($value);

        if (1 !== preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/D', $value)) {
            throw new ConfigurationException('CUPS_SERVER_KEY must contain only lowercase letters, numbers, dots, underscores, or hyphens.');
        }

        return $value;
    }

    private static function locale(string $name, string $value): string
    {
        return self::choice($name, trim($value), self::SUPPORTED_LOCALES);
    }

    /**
     * @return list<string>
     */
    private static function locales(string $value): array
    {
        $locales = array_values(array_unique(array_filter(array_map(trim(...), explode(',', $value)))));

        if ([] === $locales) {
            throw new ConfigurationException('APP_ENABLED_LOCALES must contain at least one supported locale.');
        }

        foreach ($locales as $locale) {
            self::locale('APP_ENABLED_LOCALES', $locale);
        }

        return $locales;
    }

    private static function absolutePath(string $name, string $value): string
    {
        $value = trim($value);
        $isUnix = str_starts_with($value, '/');
        $isWindows = 1 === preg_match('/^[A-Za-z]:[\\\\\/]/D', $value);

        if ('' === $value || (!$isUnix && !$isWindows) || str_contains($value, "\0")) {
            throw new ConfigurationException(sprintf('%s must be an absolute filesystem path.', $name));
        }

        return $value;
    }
}
