<?php

declare(strict_types=1);

use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Infrastructure\Configuration\ConfigurationLoader;
use EasyPrint\Infrastructure\Cups\LpstatOutputParser;
use EasyPrint\Infrastructure\Cups\LpstatQueueDiscovery;
use EasyPrint\Infrastructure\Filesystem\RuntimeDirectories;
use EasyPrint\Infrastructure\Process\AllowedProcessRunner;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$config = ConfigurationLoader::load(projectRoot: $root);
RuntimeDirectories::ensure($config->databasePath, $config->temporaryPath);

$runner = new AllowedProcessRunner(
    allowedExecutables: $config->cupsExecutables,
    workingDirectory: $config->temporaryPath,
    timeoutSeconds: $config->processTimeoutSeconds,
    maximumOutputBytes: $config->processOutputMaxBytes,
);
$discovery = new LpstatQueueDiscovery(
    processRunner: $runner,
    parser: new LpstatOutputParser(),
    host: $config->cupsHost,
    port: $config->cupsPort,
    requireEncryption: 'required' === $config->cupsEncryption,
);
$snapshot = $discovery->discover();

echo json_encode([
    'connectivity' => $snapshot->connectivity->value,
    'defaultQueueIdentifier' => $snapshot->defaultQueueIdentifier,
    'queueIdentifiers' => $snapshot->queueIdentifiers,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;

exit(CupsConnectivity::Available === $snapshot->connectivity ? 0 : 1);
