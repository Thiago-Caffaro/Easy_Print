<?php

declare(strict_types=1);

/**
 * @var Closure(string): string $escape
 * @var string                  $locale
 * @var string                  $pageTitle
 * @var string                  $heading
 * @var string                  $message
 * @var ?int                    $cupsJobId
 * @var string                  $jobIdLabel
 * @var ?string                 $error
 * @var string                  $backUrl
 * @var string                  $backLabel
 * @var string                  $historyUrl
 * @var string                  $historyLabel
 * @var string                  $stylesheetUrl
 */
?>
<!doctype html>
<html lang="<?= $escape($locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= $escape($stylesheetUrl) ?>">
</head>
<body>
    <main class="shell">
        <section class="card" aria-labelledby="result-heading">
            <p class="eyebrow">Easy Print</p>
            <h1 id="result-heading"><?= $escape($heading) ?></h1>
            <p class="description"><?= $escape($message) ?></p>
            <?php if (null !== $cupsJobId): ?>
                <p class="result-detail"><?= $escape($jobIdLabel) ?>: <strong><?= $escape((string) $cupsJobId) ?></strong></p>
            <?php endif; ?>
            <?php if (null !== $error): ?>
                <p class="history-error"><?= $escape($error) ?></p>
            <?php endif; ?>
            <nav class="primary-navigation" aria-label="Easy Print">
                <a href="<?= $escape($backUrl) ?>"><?= $escape($backLabel) ?></a>
                <a href="<?= $escape($historyUrl) ?>"><?= $escape($historyLabel) ?></a>
            </nav>
        </section>
    </main>
</body>
</html>
