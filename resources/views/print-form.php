<?php

declare(strict_types=1);

/**
 * @var Closure(string): string $escape
 * @var string                  $locale
 * @var list<array{identifier:string,selected:bool}> $queues
 * @var ?string                 $selectedQueue
 * @var ?string                 $capabilityFingerprint
 * @var bool                    $available
 * @var list<array{identifier:string,label:string,choices:list<string>,default:?string}> $basicOptions
 * @var list<array{identifier:string,label:string,choices:list<string>,default:?string}> $advancedOptions
 * @var string                  $formUrl
 * @var string                  $refreshUrl
 * @var mixed                   $csrfToken
 * @var string                  $submissionKey
 * @var array<string,string>    $labels
 */
?>
<section id="print-form" class="print-form" aria-labelledby="print-form-heading">
    <h2 id="print-form-heading"><?= $escape($labels['heading']) ?></h2>

    <?php if ([] === $queues): ?>
        <p class="empty-state"><?= $escape($labels['no_queue']) ?></p>
    <?php elseif (!$available || null === $selectedQueue || null === $capabilityFingerprint): ?>
        <p class="empty-state error-state"><?= $escape($labels['capabilities_unavailable']) ?></p>
    <?php else: ?>
        <form action="<?= $escape($formUrl) ?>" method="post" enctype="multipart/form-data" class="print-form-fields">
            <input type="hidden" name="_csrf" value="<?= $escape((string) $csrfToken) ?>">
            <input type="hidden" name="lang" value="<?= $escape($locale) ?>">
            <input type="hidden" name="submission_key" value="<?= $escape($submissionKey) ?>">
            <input type="hidden" name="capability_fingerprint" value="<?= $escape($capabilityFingerprint) ?>">

            <div class="field">
                <label for="print-queue"><?= $escape($labels['queue']) ?></label>
                <select
                    id="print-queue"
                    name="queue"
                    hx-get="<?= $escape($refreshUrl) ?>"
                    hx-trigger="change"
                    hx-target="#print-form"
                    hx-swap="outerHTML"
                    hx-include="#print-queue"
                >
                    <?php foreach ($queues as $queue): ?>
                        <option value="<?= $escape($queue['identifier']) ?>" <?= $queue['selected'] ? 'selected' : '' ?>>
                            <?= $escape($queue['identifier']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="print-document"><?= $escape($labels['document']) ?></label>
                <input id="print-document" name="document" type="file" accept="application/pdf,image/png,image/jpeg,.pdf,.png,.jpg,.jpeg" required>
                <p class="field-hint"><?= $escape($labels['document_hint']) ?></p>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="print-copies"><?= $escape($labels['copies']) ?></label>
                    <input id="print-copies" name="copies" type="number" min="1" max="999" value="1" required>
                </div>
                <div class="field">
                    <label for="print-page-range"><?= $escape($labels['page_range']) ?></label>
                    <input id="print-page-range" name="page_range" type="text" inputmode="numeric" pattern="[0-9,-]*" maxlength="512">
                    <p class="field-hint"><?= $escape($labels['page_range_hint']) ?></p>
                </div>
            </div>

            <?php foreach ($basicOptions as $option): ?>
                <?php require __DIR__ . '/partials/capability-select.php'; ?>
            <?php endforeach; ?>

            <?php if ([] !== $advancedOptions): ?>
                <details class="advanced-options">
                    <summary><?= $escape($labels['advanced']) ?></summary>
                    <?php foreach ($advancedOptions as $option): ?>
                        <?php require __DIR__ . '/partials/capability-select.php'; ?>
                    <?php endforeach; ?>
                </details>
            <?php endif; ?>

            <button class="button-primary" type="submit"><?= $escape($labels['submit']) ?></button>
        </form>
    <?php endif; ?>
</section>
