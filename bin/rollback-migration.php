<?php

declare(strict_types=1);

use EasyPrint\Infrastructure\Configuration\ConfigurationLoader;
use EasyPrint\Infrastructure\Filesystem\RuntimeDirectories;
use EasyPrint\Infrastructure\Persistence\Migrator;
use EasyPrint\Infrastructure\Persistence\SqliteConnectionFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$config = ConfigurationLoader::load(projectRoot: $root);
RuntimeDirectories::ensure($config->databasePath, $config->temporaryPath);
$connection = SqliteConnectionFactory::create($config->databasePath);
$migrator = new Migrator($connection, $root . '/database/migrations');
$version = $migrator->rollbackLast();

fwrite(STDOUT, null === $version ? "No migration to roll back.\n" : "Rolled back migration {$version}.\n");
