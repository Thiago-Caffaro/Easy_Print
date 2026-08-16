<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__ . '/app',
        __DIR__ . '/bin',
        __DIR__ . '/config',
        __DIR__ . '/public',
        __DIR__ . '/resources',
        __DIR__ . '/tests',
    ])
    ->name('*.php');

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2x0' => true,
        '@PHP8x5Migration' => true,
        'array_syntax' => ['syntax' => 'short'],
        'declare_strict_types' => true,
        'fully_qualified_strict_types' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
        'no_superfluous_phpdoc_tags' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'strict_comparison' => true,
        'strict_param' => true,
    ])
    ->setFinder($finder);
