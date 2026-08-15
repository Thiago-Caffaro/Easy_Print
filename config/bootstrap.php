<?php

declare(strict_types=1);

use EasyPrint\Application\Printer\QueueDiscovery;
use EasyPrint\Application\Printer\QueueSelectionResolver;
use EasyPrint\Http\Action\HomeAction;
use EasyPrint\Http\QueueSelectionCookie;
use EasyPrint\Infrastructure\Configuration\ConfigurationLoader;
use EasyPrint\Infrastructure\Cups\LpstatOutputParser;
use EasyPrint\Infrastructure\Cups\LpstatQueueDiscovery;
use EasyPrint\Infrastructure\Filesystem\RuntimeDirectories;
use EasyPrint\Infrastructure\Process\AllowedProcessRunner;
use EasyPrint\Translation\LocaleResolver;
use EasyPrint\Translation\Translator;
use EasyPrint\Views\PhpRenderer;
use Slim\Factory\AppFactory;

return static function (
    ?array $environment = null,
    ?string $projectRoot = null,
    ?QueueDiscovery $queueDiscovery = null,
): Slim\App {
    $root = $projectRoot ?? dirname(__DIR__);
    $config = ConfigurationLoader::load($environment, $root);
    RuntimeDirectories::ensure($config->databasePath, $config->temporaryPath);

    $translator = Translator::fromDirectory(
        $root . '/resources/translations',
        $config->enabledLocales,
        'pt-BR',
    );
    $localeResolver = new LocaleResolver($config->defaultLocale, $config->enabledLocales);
    $renderer = new PhpRenderer($root . '/resources/views');
    $queueDiscovery ??= new LpstatQueueDiscovery(
        processRunner: new AllowedProcessRunner(
            allowedExecutables: $config->cupsExecutables,
            workingDirectory: $config->temporaryPath,
            timeoutSeconds: $config->processTimeoutSeconds,
            maximumOutputBytes: $config->processOutputMaxBytes,
        ),
        parser: new LpstatOutputParser(),
        host: $config->cupsHost,
        port: $config->cupsPort,
        requireEncryption: 'required' === $config->cupsEncryption,
    );
    $homeAction = new HomeAction(
        config: $config,
        queueDiscovery: $queueDiscovery,
        selectionResolver: new QueueSelectionResolver(),
        selectionCookie: new QueueSelectionCookie($config->basePath),
        localeResolver: $localeResolver,
        translator: $translator,
        renderer: $renderer,
    );

    $app = AppFactory::create();

    if ('' !== $config->basePath) {
        $app->setBasePath($config->basePath);
    }

    $app->get('/', $homeAction);
    $app->addRoutingMiddleware();
    $app->addErrorMiddleware(false, true, true);

    return $app;
};
