<?php

declare(strict_types=1);

/**
 * @var Closure(string): string $escape
 * @var string                  $locale
 * @var string                  $pageTitle
 * @var string                  $heading
 * @var string                  $description
 * @var string                  $statusLabel
 * @var string                  $statusValue
 * @var string                  $environmentLabel
 * @var string                  $environmentValue
 * @var string                  $languageLabel
 * @var string                  $portugueseLabel
 * @var string                  $englishLabel
 */
?>
<!doctype html>
<html lang="<?= $escape($locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
    <main class="shell">
        <section class="card" aria-labelledby="page-heading">
            <p class="eyebrow">Easy Print</p>
            <h1 id="page-heading"><?= $escape($heading) ?></h1>
            <p class="description"><?= $escape($description) ?></p>

            <dl class="status-grid">
                <div>
                    <dt><?= $escape($statusLabel) ?></dt>
                    <dd><span class="status-dot" aria-hidden="true"></span><?= $escape($statusValue) ?></dd>
                </div>
                <div>
                    <dt><?= $escape($environmentLabel) ?></dt>
                    <dd><?= $escape($environmentValue) ?></dd>
                </div>
            </dl>

            <nav aria-label="<?= $escape($languageLabel) ?>" class="languages">
                <a href="?lang=pt-BR" lang="pt-BR" hreflang="pt-BR"><?= $escape($portugueseLabel) ?></a>
                <a href="?lang=en" lang="en" hreflang="en"><?= $escape($englishLabel) ?></a>
            </nav>
        </section>
    </main>
</body>
</html>
