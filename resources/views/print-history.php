<?php

declare(strict_types=1);

/**
 * @var Closure(string): string $escape
 * @var string $locale
 * @var string $pageTitle
 * @var string $heading
 * @var string $description
 * @var string $backLabel
 * @var string $backUrl
 * @var string $stylesheetUrl
 * @var bool $available
 * @var string $emptyMessage
 * @var string $unavailableMessage
 * @var list<array<string,?string>> $entries
 * @var array{queue:string,jobId:string,type:string,size:string,copies:string,pages:string,options:string,state:string,submittedAt:string} $labels
 * @var ?string $previousUrl
 * @var ?string $nextUrl
 * @var string $previousLabel
 * @var string $nextLabel
 * @var string $pageLabel
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
        <section class="card card-wide" aria-labelledby="history-heading">
            <p class="eyebrow">Easy Print</p>
            <h1 id="history-heading"><?= $escape($heading) ?></h1>
            <p class="description"><?= $escape($description) ?></p>
            <p><a class="back-link" href="<?= $escape($backUrl) ?>"><?= $escape($backLabel) ?></a></p>

            <?php if (!$available): ?>
                <p class="empty-state error-state" role="alert"><?= $escape($unavailableMessage) ?></p>
            <?php elseif ([] === $entries): ?>
                <p class="empty-state"><?= $escape($emptyMessage) ?></p>
            <?php else: ?>
                <ol class="history-list">
                    <?php foreach ($entries as $entry): ?>
                        <li class="history-card">
                            <div class="history-card-heading">
                                <h2><?= $escape($entry['title']) ?></h2>
                                <span class="badge"><?= $escape($entry['state']) ?></span>
                            </div>
                            <dl class="job-details">
                                <?php foreach ($labels as $key => $label): ?>
                                    <div>
                                        <dt><?= $escape($label) ?></dt>
                                        <dd><?= $escape($entry[$key]) ?></dd>
                                    </div>
                                <?php endforeach; ?>
                            </dl>
                            <?php if (null !== $entry['error']): ?>
                                <p class="history-error"><?= $escape($entry['error']) ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

            <?php if ($available && [] !== $entries): ?>
                <nav class="pagination" aria-label="<?= $escape($pageLabel) ?>">
                    <?php if (null !== $previousUrl): ?>
                        <a href="<?= $escape($previousUrl) ?>" rel="prev"><?= $escape($previousLabel) ?></a>
                    <?php endif; ?>
                    <span><?= $escape($pageLabel) ?></span>
                    <?php if (null !== $nextUrl): ?>
                        <a href="<?= $escape($nextUrl) ?>" rel="next"><?= $escape($nextLabel) ?></a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
