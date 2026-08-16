<?php

declare(strict_types=1);

/**
 * @var Closure(string): string $escape
 * @var string $heading
 * @var bool $available
 * @var string $unavailableMessage
 * @var string $queueLabel
 * @var string $queueIdentifier
 * @var string $stateLabel
 * @var string $stateValue
 * @var string $acceptingLabel
 * @var string $acceptingValue
 * @var string $reasonsHeading
 * @var string $readyMessage
 * @var list<string> $reasons
 * @var string $pollUrl
 * @var string $pollTrigger
 */
?>
<section
    id="printer-status"
    class="printer-status"
    aria-labelledby="printer-status-heading"
    aria-live="polite"
    hx-get="<?= $escape($pollUrl) ?>"
    hx-trigger="<?= $escape($pollTrigger) ?>"
    hx-swap="outerHTML"
>
    <h2 id="printer-status-heading"><?= $escape($heading) ?></h2>
    <?php if (!$available): ?>
        <p class="empty-state error-state"><?= $escape($unavailableMessage) ?></p>
    <?php else: ?>
        <dl class="job-details">
            <div><dt><?= $escape($queueLabel) ?></dt><dd><?= $escape($queueIdentifier) ?></dd></div>
            <div><dt><?= $escape($stateLabel) ?></dt><dd><?= $escape($stateValue) ?></dd></div>
            <div><dt><?= $escape($acceptingLabel) ?></dt><dd><?= $escape($acceptingValue) ?></dd></div>
        </dl>
        <h3><?= $escape($reasonsHeading) ?></h3>
        <?php if ([] === $reasons): ?>
            <p class="empty-state"><?= $escape($readyMessage) ?></p>
        <?php else: ?>
            <ul class="reason-list">
                <?php foreach ($reasons as $reason): ?>
                    <li><?= $escape($reason) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</section>
