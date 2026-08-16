<?php

declare(strict_types=1);

use EasyPrint\Application\Health\ReadinessProbe;
use EasyPrint\Application\Printer\QueueDiscovery;
use EasyPrint\Application\Printer\QueueSelectionResolver;
use EasyPrint\Http\Action\HomeAction;
use EasyPrint\Http\Action\LivenessAction;
use EasyPrint\Http\Action\ReadinessAction;
use EasyPrint\Http\Middleware\CorrelationIdMiddleware;
use EasyPrint\Http\Middleware\CsrfProtectionMiddleware;
use EasyPrint\Http\Middleware\ExceptionLoggingMiddleware;
use EasyPrint\Http\Middleware\RequestLimitsMiddleware;
use EasyPrint\Http\Middleware\SecurityHeadersMiddleware;
use EasyPrint\Http\QueueSelectionCookie;
use EasyPrint\Http\Security\CsrfTokenManager;
use EasyPrint\Infrastructure\Configuration\ConfigurationLoader;
use EasyPrint\Infrastructure\Cups\LpstatOutputParser;
use EasyPrint\Infrastructure\Cups\LpstatQueueDiscovery;
use EasyPrint\Infrastructure\Filesystem\RuntimeDirectories;
use EasyPrint\Infrastructure\Health\OperationalReadinessProbe;
use EasyPrint\Infrastructure\Observability\CorrelationContext;
use EasyPrint\Infrastructure\Observability\JsonLineLogger;
use EasyPrint\Infrastructure\Process\AllowedProcessRunner;
use EasyPrint\Infrastructure\Security\RuntimeSecret;
use EasyPrint\Translation\LocaleResolver;
use EasyPrint\Translation\Translator;
use EasyPrint\Views\PhpRenderer;
use Slim\Factory\AppFactory;

return static function (
    ?array $environment = null,
    ?string $projectRoot = null,
    ?QueueDiscovery $queueDiscovery = null,
    ?ReadinessProbe $readinessProbe = null,
): Slim\App {
    $root = $projectRoot ?? dirname(__DIR__);
    $config = ConfigurationLoader::load($environment, $root);
    RuntimeDirectories::ensure($config->databasePath, $config->temporaryPath);
    $correlationContext = new CorrelationContext();
    $logger = JsonLineLogger::toStderr($correlationContext, $config->logLevel);

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
        logger: $logger,
    );
    $readinessProbe ??= new OperationalReadinessProbe(
        databasePath: $config->databasePath,
        temporaryPath: $config->temporaryPath,
        queueDiscovery: $queueDiscovery,
        logger: $logger,
    );
    $homeAction = new HomeAction(
        config: $config,
        queueDiscovery: $queueDiscovery,
        selectionResolver: new QueueSelectionResolver(),
        selectionCookie: new QueueSelectionCookie($config->basePath, $config->cookieSecure),
        localeResolver: $localeResolver,
        translator: $translator,
        renderer: $renderer,
    );

    $app = AppFactory::create();

    if ('' !== $config->basePath) {
        $app->setBasePath($config->basePath);
    }

    $app->get('/', $homeAction);
    $app->get('/health/live', new LivenessAction());
    $app->get('/health/ready', new ReadinessAction($readinessProbe));
    $app->addRoutingMiddleware();
    $app->add(new CsrfProtectionMiddleware(
        tokens: new CsrfTokenManager(RuntimeSecret::loadOrCreate($config->temporaryPath . '/csrf-secret')),
        responses: $app->getResponseFactory(),
        basePath: $config->basePath,
        secureCookie: $config->cookieSecure,
    ));
    $app->add(new RequestLimitsMiddleware(
        responses: $app->getResponseFactory(),
        maximumBodyBytes: $config->requestBodyMaxBytes,
        maximumHeaderBytes: $config->requestHeaderMaxBytes,
    ));
    $app->add(new ExceptionLoggingMiddleware($logger));
    $app->addErrorMiddleware(false, false, false);
    $app->add(new SecurityHeadersMiddleware());
    $app->add(new CorrelationIdMiddleware($correlationContext, $logger));

    return $app;
};
