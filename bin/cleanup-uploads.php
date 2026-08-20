<?php

declare(strict_types=1);

use EasyPrint\Infrastructure\Configuration\ConfigurationLoader;
use EasyPrint\Infrastructure\Filesystem\RuntimeDirectories;
use EasyPrint\Infrastructure\Upload\TemporaryUploadCleanup;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$config = ConfigurationLoader::load(projectRoot: $root);
RuntimeDirectories::ensure($config->databasePath, $config->temporaryPath);
$report = new TemporaryUploadCleanup($config->temporaryPath, $config->temporaryFileTtlSeconds)->run();

fwrite(STDOUT, sprintf(
    "Temporary uploads cleaned: deleted=%d skipped=%d failed=%d\n",
    $report->deleted,
    $report->skipped,
    $report->failed,
));

exit($report->failed > 0 ? 1 : 0);
