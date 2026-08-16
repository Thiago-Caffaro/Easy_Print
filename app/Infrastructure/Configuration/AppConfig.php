<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Configuration;

final readonly class AppConfig
{
    /**
     * @param list<string>         $enabledLocales
     * @param array<string,string> $cupsExecutables
     */
    public function __construct(
        public string $environment,
        public bool $debug,
        public string $basePath,
        public string $defaultLocale,
        public array $enabledLocales,
        public string $cupsHost,
        public int $cupsPort,
        public string $cupsEncryption,
        public string $cupsServerKey,
        public string $databasePath,
        public string $temporaryPath,
        public int $uploadMaxBytes,
        public int $imageMaxWidth,
        public int $imageMaxHeight,
        public int $imageMaxPixels,
        public int $temporaryFileTtlSeconds,
        public int $historyRetentionDays,
        public int $errorRetentionDays,
        public int $capabilityCacheTtlSeconds,
        public int $processTimeoutSeconds,
        public int $processOutputMaxBytes,
        public array $cupsExecutables,
    ) {}
}
