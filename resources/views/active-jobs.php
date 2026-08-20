<?php

declare(strict_types=1);

/**
 * @var Closure(string): string $escape
 * @var string                  $heading
 * @var string                  $connectivityLabel
 * @var string                  $errorMessage
 * @var string                  $emptyMessage
 * @var string                  $refreshLabel
 * @var string                  $jobIdLabel
 * @var string                  $queueLabel
 * @var string                  $submittedAtLabel
 * @var string                  $sizeLabel
 * @var string                  $stateLabel
 * @var bool                    $available
 * @var list<array{cupsJobId:string,queueIdentifier:string,title:string,submittedAt:string,byteSize:string,stateLabel:string}> $jobs
 * @var string                  $pollUrl
 * @var ?string                 $pollTrigger
 * @var string|null             $csrfToken
 * @var string                  $cancelLabel
 * @var string                  $cancelConfirm
 * @var string|null             $cancelNotice
 */
?>
<section
    id="active-jobs"
    class="active-jobs"
    aria-labelledby="active-jobs-heading"
    aria-live="polite"
    <?php if (null !== $pollTrigger): ?>
        hx-get="<?= $escape($pollUrl) ?>"
        hx-trigger="<?= $escape($pollTrigger) ?>"
        hx-swap="outerHTML"
    <?php endif; ?>
>
    <div class="section-heading">
        <div>
            <p class="section-status"><?= $escape($connectivityLabel) ?></p>
            <h2 id="active-jobs-heading"><?= $escape($heading) ?></h2>
        </div>
        <a href="<?= $escape($pollUrl) ?>" hx-get="<?= $escape($pollUrl) ?>" hx-target="#active-jobs" hx-swap="outerHTML">
            <?= $escape($refreshLabel) ?>
        </a>
    </div>

    <?php if (null !== $cancelNotice): ?>
        <p class="empty-state" role="status"><?= $escape($cancelNotice) ?></p>
    <?php endif; ?>

    <?php if (!$available): ?>
        <p class="empty-state error-state"><?= $escape($errorMessage) ?></p>
    <?php elseif ([] === $jobs): ?>
        <p class="empty-state"><?= $escape($emptyMessage) ?></p>
    <?php else: ?>
        <ul class="job-list">
            <?php foreach ($jobs as $job): ?>
                <li class="job-card">
                    <h3><?= $escape($job['title']) ?></h3>
                    <dl class="job-details">
                        <div>
                            <dt><?= $escape($jobIdLabel) ?></dt>
                            <dd><?= $escape($job['cupsJobId']) ?></dd>
                        </div>
                        <div>
                            <dt><?= $escape($queueLabel) ?></dt>
                            <dd><?= $escape($job['queueIdentifier']) ?></dd>
                        </div>
                        <div>
                            <dt><?= $escape($submittedAtLabel) ?></dt>
                            <dd><?= $escape($job['submittedAt']) ?></dd>
                        </div>
                        <div>
                            <dt><?= $escape($sizeLabel) ?></dt>
                            <dd><?= $escape($job['byteSize']) ?></dd>
                        </div>
                        <div>
                            <dt><?= $escape($stateLabel) ?></dt>
                            <dd><?= $escape($job['stateLabel']) ?></dd>
                        </div>
                    </dl>
                    <?php if ($job['cancelable'] && is_string($csrfToken)): ?>
                        <form method="post" action="<?= $escape($job['cancelUrl']) ?>" class="job-cancel-form">
                            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                            <button type="submit" class="button button-secondary" onclick="return confirm('<?= $escape($cancelConfirm) ?>');">
                                <?= $escape($cancelLabel) ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
