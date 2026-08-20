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
 * @var string                  $cupsLabel
 * @var string                  $cupsValue
 * @var string                  $queuesHeading
 * @var string                  $noQueuesMessage
 * @var string                  $selectedLabel
 * @var string                  $defaultLabel
 * @var list<array{identifier:string,stateLabel:string,selected:bool,default:bool,href:string}> $queues
 * @var string                  $languageLabel
 * @var string                  $portugueseLabel
 * @var string                  $englishLabel
 * @var string                  $stylesheetUrl
 * @var string                  $htmxAssetUrl
 * @var string                  $activeJobsUrl
 * @var string                  $activeJobsHeading
 * @var string                  $activeJobsLoading
 * @var string                  $historyUrl
 * @var string                  $historyLabel
 * @var ?string                 $printerStatusUrl
 * @var string                  $printerStatusHeading
 * @var string                  $printerStatusLoading
 * @var string                  $printerStatusNoSelection
 * @var string                  $printFormHtml
 */
?>
<!doctype html>
<html lang="<?= $escape($locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= $escape($stylesheetUrl) ?>">
    <script src="<?= $escape($htmxAssetUrl) ?>" defer></script>
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
                <div>
                    <dt><?= $escape($cupsLabel) ?></dt>
                    <dd><?= $escape($cupsValue) ?></dd>
                </div>
            </dl>

            <section class="queues" aria-labelledby="queues-heading">
                <h2 id="queues-heading"><?= $escape($queuesHeading) ?></h2>

                <?php if ([] === $queues): ?>
                    <p class="empty-state"><?= $escape($noQueuesMessage) ?></p>
                <?php else: ?>
                    <ul class="queue-list">
                        <?php foreach ($queues as $queue): ?>
                            <li>
                                <a
                                    class="queue-option<?= $queue['selected'] ? ' is-selected' : '' ?>"
                                    href="<?= $escape($queue['href']) ?>"
                                    <?= $queue['selected'] ? 'aria-current="true"' : '' ?>
                                >
                                    <span>
                                        <strong><?= $escape($queue['identifier']) ?></strong>
                                        <small><?= $escape($queue['stateLabel']) ?></small>
                                    </span>
                                    <span class="queue-badges">
                                        <?php if ($queue['default']): ?>
                                            <span class="badge"><?= $escape($defaultLabel) ?></span>
                                        <?php endif; ?>
                                        <?php if ($queue['selected']): ?>
                                            <span class="badge badge-selected"><?= $escape($selectedLabel) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <?= $printFormHtml ?>

            <section
                id="active-jobs"
                class="active-jobs"
                aria-labelledby="active-jobs-heading"
                aria-live="polite"
                hx-get="<?= $escape($activeJobsUrl) ?>"
                hx-trigger="load"
                hx-swap="outerHTML"
            >
                <h2 id="active-jobs-heading"><?= $escape($activeJobsHeading) ?></h2>
                <p class="empty-state">
                    <a href="<?= $escape($activeJobsUrl) ?>"><?= $escape($activeJobsLoading) ?></a>
                </p>
            </section>

            <?php if (null === $printerStatusUrl): ?>
                <section class="printer-status" aria-labelledby="printer-status-heading">
                    <h2 id="printer-status-heading"><?= $escape($printerStatusHeading) ?></h2>
                    <p class="empty-state"><?= $escape($printerStatusNoSelection) ?></p>
                </section>
            <?php else: ?>
                <section
                    id="printer-status"
                    class="printer-status"
                    aria-labelledby="printer-status-heading"
                    aria-live="polite"
                    hx-get="<?= $escape($printerStatusUrl) ?>"
                    hx-trigger="load"
                    hx-swap="outerHTML"
                >
                    <h2 id="printer-status-heading"><?= $escape($printerStatusHeading) ?></h2>
                    <p class="empty-state"><?= $escape($printerStatusLoading) ?></p>
                </section>
            <?php endif; ?>

            <nav class="primary-navigation" aria-label="Easy Print">
                <a href="<?= $escape($historyUrl) ?>"><?= $escape($historyLabel) ?></a>
            </nav>

            <nav aria-label="<?= $escape($languageLabel) ?>" class="languages">
                <a href="?lang=pt-BR" lang="pt-BR" hreflang="pt-BR"><?= $escape($portugueseLabel) ?></a>
                <a href="?lang=en" lang="en" hreflang="en"><?= $escape($englishLabel) ?></a>
            </nav>
        </section>
    </main>
</body>
</html>
