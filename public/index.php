<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$createApplication = require dirname(__DIR__) . '/config/bootstrap.php';
$application = $createApplication();
$application->run();
