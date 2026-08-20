<?php

declare(strict_types=1);

use EasyPrint\Application\Health\ReadinessProbe;
use EasyPrint\Application\Printer\ActiveJobDiscovery;
use EasyPrint\Application\Printer\JobTitleLookup;
use EasyPrint\Application\Printer\PrintArgumentMapper;
use EasyPrint\Application\Printer\PrintHistoryReader;
use EasyPrint\Application\Printer\PrintJobCancellation;
use EasyPrint\Application\Printer\PrintSubmissionService;
use EasyPrint\Application\Printer\QueueCapabilityDiscovery;
use EasyPrint\Application\Printer\QueueDiscovery;
use EasyPrint\Application\Printer\QueueSelectionResolver;
use EasyPrint\Application\Printer\QueueStatusDiscovery;
use EasyPrint\Http\Action\ActiveJobsAction;
use EasyPrint\Http\Action\CancelJobAction;
use EasyPrint\Http\Action\HomeAction;
use EasyPrint\Http\Action\LivenessAction;
use EasyPrint\Http\Action\PrinterStatusAction;
use EasyPrint\Http\Action\PrintFormAction;
use EasyPrint\Http\Action\PrintHistoryAction;
use EasyPrint\Http\Action\PrintSubmissionAction;
use EasyPrint\Http\Action\ReadinessAction;
use EasyPrint\Http\Action\StaticAssetAction;
use EasyPrint\Http\Middleware\CorrelationIdMiddleware;
use EasyPrint\Http\Middleware\CsrfProtectionMiddleware;
use EasyPrint\Http\Middleware\ExceptionLoggingMiddleware;
use EasyPrint\Http\Middleware\RequestLimitsMiddleware;
use EasyPrint\Http\Middleware\SecurityHeadersMiddleware;
use EasyPrint\Http\PrintFormDataFactory;
use EasyPrint\Http\QueueSelectionCookie;
use EasyPrint\Http\Security\CsrfTokenManager;
use EasyPrint\Infrastructure\Configuration\ConfigurationLoader;
use EasyPrint\Infrastructure\Cups\LpoptionsCapabilityDiscovery;
use EasyPrint\Infrastructure\Cups\LpoptionsOutputParser;
use EasyPrint\Infrastructure\Cups\LpPrintJobSubmitter;
use EasyPrint\Infrastructure\Cups\LpstatActiveJobDiscovery;
use EasyPrint\Infrastructure\Cups\LpstatJobOutputParser;
use EasyPrint\Infrastructure\Cups\LpstatOutputParser;
use EasyPrint\Infrastructure\Cups\LpstatPrinterStatusParser;
use EasyPrint\Infrastructure\Cups\LpstatQueueDiscovery;
use EasyPrint\Infrastructure\Cups\LpstatQueueStatusDiscovery;
use EasyPrint\Infrastructure\Cups\LpSubmissionOutputParser;
use EasyPrint\Infrastructure\Filesystem\RuntimeDirectories;
use EasyPrint\Infrastructure\Health\OperationalReadinessProbe;
use EasyPrint\Infrastructure\Observability\CorrelationContext;
use EasyPrint\Infrastructure\Observability\JsonLineLogger;
use EasyPrint\Infrastructure\Persistence\SqliteConnectionFactory;
use EasyPrint\Infrastructure\Persistence\SqliteJobTitleLookup;
use EasyPrint\Infrastructure\Persistence\SqlitePrintHistoryReader;
use EasyPrint\Infrastructure\Persistence\SqlitePrintJobRepository;
use EasyPrint\Infrastructure\Process\AllowedProcessRunner;
use EasyPrint\Infrastructure\Security\RuntimeSecret;
use EasyPrint\Infrastructure\Upload\ImageFileInspector;
use EasyPrint\Infrastructure\Upload\PdfStructureInspector;
use EasyPrint\Infrastructure\Upload\SecureImageUpload;
use EasyPrint\Infrastructure\Upload\SecurePdfUpload;
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
    ?PrintHistoryReader $printHistoryReader = null,
    ?QueueStatusDiscovery $queueStatusDiscovery = null,
    ?QueueCapabilityDiscovery $queueCapabilityDiscovery = null,
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
    $printHistoryReader ??= new SqlitePrintHistoryReader($config->databasePath, $logger);
    $queueStatusDiscovery ??= new LpstatQueueStatusDiscovery(
        processRunner: $processRunner,
        parser: new LpstatPrinterStatusParser(),
        host: $config->cupsHost,
        port: $config->cupsPort,
        requireEncryption: 'required' === $config->cupsEncryption,
        logger: $logger,
    );
    $queueCapabilityDiscovery ??= new LpoptionsCapabilityDiscovery(
        processRunner: $processRunner,
        parser: new LpoptionsOutputParser(),
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
    $selectionCookie = new QueueSelectionCookie($config->basePath, $config->cookieSecure);
    $printFormFactory = new PrintFormDataFactory(
        config: $config,
        queues: $queueDiscovery,
        capabilities: $queueCapabilityDiscovery,
        selectionResolver: new QueueSelectionResolver(),
        selectionCookie: $selectionCookie,
        localeResolver: $localeResolver,
        translator: $translator,
    );
    $homeAction = new HomeAction(
        config: $config,
        queueDiscovery: $queueDiscovery,
        selectionResolver: new QueueSelectionResolver(),
        selectionCookie: $selectionCookie,
        printFormFactory: $printFormFactory,
        localeResolver: $localeResolver,
        translator: $translator,
        renderer: $renderer,
    );
    $printFormAction = new PrintFormAction($printFormFactory, $selectionCookie, $renderer);
    $printSubmissionAction = new PrintSubmissionAction(
        config: $config,
        queues: $queueDiscovery,
        capabilities: $queueCapabilityDiscovery,
        arguments: new PrintArgumentMapper(),
        pdfUpload: new SecurePdfUpload(
            storageDirectory: $config->temporaryPath,
            publicDirectory: $root . '/public',
            maximumBytes: $config->uploadMaxBytes,
            structureInspector: new PdfStructureInspector(),
        ),
        imageUpload: new SecureImageUpload(
            storageDirectory: $config->temporaryPath,
            publicDirectory: $root . '/public',
            maximumBytes: $config->uploadMaxBytes,
            maximumWidth: $config->imageMaxWidth,
            maximumHeight: $config->imageMaxHeight,
            maximumPixels: $config->imageMaxPixels,
            inspector: new ImageFileInspector(),
        ),
        submissionFactory: static fn(): PrintSubmissionService => new PrintSubmissionService(
            repository: new SqlitePrintJobRepository(SqliteConnectionFactory::create($config->databasePath)),
            submitter: new LpPrintJobSubmitter(
                processRunner: $processRunner,
                parser: new LpSubmissionOutputParser(),
                host: $config->cupsHost,
                port: $config->cupsPort,
                requireEncryption: 'required' === $config->cupsEncryption,
            ),
            historyRetentionDays: $config->historyRetentionDays,
        ),
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
    $cancelJobAction = new CancelJobAction(
        config: $config,
        cancellation: new PrintJobCancellation(
            config: $config,
            jobs: $activeJobDiscovery,
            processRunner: $processRunner,
            logger: $logger,
        ),
        localeResolver: $localeResolver,
    );
    $printHistoryAction = new PrintHistoryAction(
        config: $config,
        history: $printHistoryReader,
        localeResolver: $localeResolver,
        translator: $translator,
        renderer: $renderer,
    );
    $printerStatusAction = new PrinterStatusAction(
        config: $config,
        queues: $queueDiscovery,
        status: $queueStatusDiscovery,
        localeResolver: $localeResolver,
        translator: $translator,
        renderer: $renderer,
    );

    $app = AppFactory::create();

    if ('' !== $config->basePath) {
        $app->setBasePath($config->basePath);
    }

    $app->get('/', $homeAction);
    $app->get('/print-form', $printFormAction);
    $app->post('/print', $printSubmissionAction);
    $app->get('/jobs/active', $activeJobsAction);
    $app->post('/jobs/{queue}/cancel/{job}', $cancelJobAction);
    $app->get('/history', $printHistoryAction);
    $app->get('/printer/status', $printerStatusAction);
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
