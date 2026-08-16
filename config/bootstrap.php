<?php

declare(strict_types=1);

use EasyPrint\Application\Health\ReadinessProbe;
use EasyPrint\Application\Printer\ActiveJobDiscovery;
use EasyPrint\Application\Printer\JobTitleLookup;
use EasyPrint\Application\Printer\QueueDiscovery;
use EasyPrint\Application\Printer\QueueSelectionResolver;
use EasyPrint\Http\Action\ActiveJobsAction;
use EasyPrint\Http\Action\HomeAction;
use EasyPrint\Http\Action\LivenessAction;
use EasyPrint\Http\Action\ReadinessAction;
use EasyPrint\Http\Action\StaticAssetAction;
use EasyPrint\Http\Middleware\CorrelationIdMiddleware;
use EasyPrint\Http\Middleware\CsrfProtectionMiddleware;
use EasyPrint\Http\Middleware\ExceptionLoggingMiddleware;
use EasyPrint\Http\Middleware\RequestLimitsMiddleware;
use EasyPrint\Http\Middleware\SecurityHeadersMiddleware;
use EasyPrint\Http\QueueSelectionCookie;
use EasyPrint\Http\Security\CsrfTokenManager;
use EasyPrint\Infrastructure\Configuration\ConfigurationLoader;
use EasyPrint\Infrastructure\Cups\LpstatActiveJobDiscovery;
use EasyPrint\Infrastructure\Cups\LpstatJobOutputParser;
use EasyPrint\Infrastructure\Cups\LpstatOutputParser;
use EasyPrint\Infrastructure\Cups\LpstatQueueDiscovery;
use EasyPrint\Infrastructure\Filesystem\RuntimeDirectories;
use EasyPrint\Infrastructure\Health\OperationalReadinessProbe;
use EasyPrint\Infrastructure\Observability\CorrelationContext;
use EasyPrint\Infrastructure\Observability\JsonLineLogger;
use EasyPrint\Infrastructure\Persistence\SqliteJobTitleLookup;
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
    ?ActiveJobDiscovery $activeJobDiscovery = null,
    ?JobTitleLookup $jobTitleLookup = null,
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
    $processRunner = new AllowedProcessRunner(
        allowedExecutables: $config->cupsExecutables,
        workingDirectory: $config->temporaryPath,
        timeoutSeconds: $config->processTimeoutSeconds,
        maximumOutputBytes: $config->processOutputMaxBytes,
    );
    $queueDiscovery ??= new LpstatQueueDiscovery(
        processRunner: $processRunner,
        parser: new LpstatOutputParser(),
        host: $config->cupsHost,
        port: $config->cupsPort,
        requireEncryption: 'required' === $config->cupsEncryption,
        logger: $logger,
    );
    $activeJobDiscovery ??= new LpstatActiveJobDiscovery(
        processRunner: $processRunner,
        jobParser: new LpstatJobOutputParser(),
        printerParser: new LpstatOutputParser(),
        host: $config->cupsHost,
        port: $config->cupsPort,
        requireEncryption: 'required' === $config->cupsEncryption,
        logger: $logger,
    );
    $jobTitleLookup ??= new SqliteJobTitleLookup(
        $config->databasePath,
        $logger,
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
    $activeJobsAction = new ActiveJobsAction(
        config: $config,
        discovery: $activeJobDiscovery,
        titleLookup: $jobTitleLookup,
        localeResolver: $localeResolver,
        translator: $translator,
        renderer: $renderer,
    );

    $app = AppFactory::create();

    if ('' !== $config->basePath) {
        $app->setBasePath($config->basePath);
    }

    $app->get('/', $homeAction);
    $app->get('/jobs/active', $activeJobsAction);
    $app->get('/assets/app.css', new StaticAssetAction($root . '/public/assets/app.css', 'text/css; charset=UTF-8'));
    $app->get('/assets/htmx.min.js', new StaticAssetAction($root . '/public/assets/htmx.min.js', 'text/javascript; charset=UTF-8'));
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
