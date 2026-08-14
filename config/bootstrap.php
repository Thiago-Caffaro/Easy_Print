<?php

declare(strict_types=1);

use EasyPrint\Http\Action\HomeAction;
use EasyPrint\Infrastructure\Configuration\ConfigurationLoader;
use EasyPrint\Infrastructure\Filesystem\RuntimeDirectories;
use EasyPrint\Translation\LocaleResolver;
use EasyPrint\Translation\Translator;
use EasyPrint\Views\PhpRenderer;
use Slim\Factory\AppFactory;

return static function (?array $environment = null, ?string $projectRoot = null): Slim\App {
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
    $homeAction = new HomeAction($config, $localeResolver, $translator, $renderer);

    $app = AppFactory::create();

    if ('' !== $config->basePath) {
        $app->setBasePath($config->basePath);
    }

    $app->get('/', $homeAction);
    $app->addRoutingMiddleware();
    $app->addErrorMiddleware(false, true, true);

    return $app;
};
