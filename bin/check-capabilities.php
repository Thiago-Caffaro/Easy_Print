<?php

declare(strict_types=1);

use EasyPrint\Application\Printer\CachedQueueCapabilityDiscovery;
use EasyPrint\Domain\Printer\CapabilityChoice;
use EasyPrint\Domain\Printer\CapabilityOption;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Infrastructure\Configuration\ConfigurationLoader;
use EasyPrint\Infrastructure\Cups\LpoptionsCapabilityDiscovery;
use EasyPrint\Infrastructure\Cups\LpoptionsOutputParser;
use EasyPrint\Infrastructure\Cups\LpstatOutputParser;
use EasyPrint\Infrastructure\Cups\LpstatQueueDiscovery;
use EasyPrint\Infrastructure\Filesystem\RuntimeDirectories;
use EasyPrint\Infrastructure\Observability\CorrelationContext;
use EasyPrint\Infrastructure\Observability\JsonLineLogger;
use EasyPrint\Infrastructure\Persistence\CapabilitySnapshotCodec;
use EasyPrint\Infrastructure\Persistence\SqliteCapabilitySnapshotCache;
use EasyPrint\Infrastructure\Persistence\SqliteConnectionFactory;
use EasyPrint\Infrastructure\Process\AllowedProcessRunner;

require dirname(__DIR__) . '/vendor/autoload.php';

$arguments = $_SERVER['argv'] ?? [];
$queueIdentifier = is_array($arguments) ? ($arguments[1] ?? null) : null;

if (!is_array($arguments) || !is_string($queueIdentifier) || '' === $queueIdentifier || 2 !== count($arguments)) {
    fwrite(STDERR, "Usage: php bin/check-capabilities.php <queue>\n");

    exit(2);
}

$root = dirname(__DIR__);
$config = ConfigurationLoader::load(projectRoot: $root);
RuntimeDirectories::ensure($config->databasePath, $config->temporaryPath);
$logger = JsonLineLogger::toStderr(new CorrelationContext(), $config->logLevel);
$runner = new AllowedProcessRunner(
    allowedExecutables: $config->cupsExecutables,
    workingDirectory: $config->temporaryPath,
    timeoutSeconds: $config->processTimeoutSeconds,
    maximumOutputBytes: $config->processOutputMaxBytes,
);
$queues = new LpstatQueueDiscovery(
    processRunner: $runner,
    parser: new LpstatOutputParser(),
    host: $config->cupsHost,
    port: $config->cupsPort,
    requireEncryption: 'required' === $config->cupsEncryption,
    logger: $logger,
);
$queueSnapshot = $queues->discover();

if (CupsConnectivity::Available !== $queueSnapshot->connectivity || !$queueSnapshot->contains($queueIdentifier)) {
    echo json_encode([
        'connectivity' => $queueSnapshot->connectivity->value,
        'error' => 'queue_not_available',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;

    exit(1);
}

$source = new LpoptionsCapabilityDiscovery(
    processRunner: $runner,
    parser: new LpoptionsOutputParser(),
    host: $config->cupsHost,
    port: $config->cupsPort,
    requireEncryption: 'required' === $config->cupsEncryption,
    logger: $logger,
);
$capabilities = new CachedQueueCapabilityDiscovery(
    source: $source,
    cache: new SqliteCapabilitySnapshotCache(
        SqliteConnectionFactory::create($config->databasePath),
        new CapabilitySnapshotCodec(),
    ),
    serverKey: $config->cupsServerKey,
    ttlSeconds: $config->capabilityCacheTtlSeconds,
);
$snapshot = $capabilities->discover($queueIdentifier);

echo json_encode([
    'connectivity' => $snapshot->connectivity->value,
    'queueIdentifier' => $snapshot->queueIdentifier,
    'fingerprint' => $snapshot->fingerprint,
    'options' => array_map(static fn(CapabilityOption $option): array => [
        'technicalIdentifier' => $option->technicalIdentifier,
        'category' => $option->category->value,
        'defaultTechnicalIdentifier' => $option->defaultTechnicalIdentifier,
        'choices' => array_map(
            static fn(CapabilityChoice $choice): string => $choice->technicalIdentifier,
            $option->choices,
        ),
        'renderable' => $option->isRenderable(),
    ], $snapshot->options),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;

exit(CupsConnectivity::Available === $snapshot->connectivity ? 0 : 1);
