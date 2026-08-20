<?php

declare(strict_types=1);

/**
 * @var Closure(string): string $escape
 * @var array{identifier:string,label:string,choices:list<string>,default:?string} $option
 * @var array<string,string> $labels
 */
?>
<div class="field">
    <label for="option-<?= $escape($option['identifier']) ?>"><?= $escape($option['label']) ?></label>
    <select id="option-<?= $escape($option['identifier']) ?>" name="options[<?= $escape($option['identifier']) ?>]">
        <?php foreach ($option['choices'] as $choice): ?>
            <option value="<?= $escape($choice) ?>" <?= $choice === $option['default'] ? 'selected' : '' ?>>
                <?= $escape($choice) ?><?= $choice === $option['default'] ? ' — ' . $escape($labels['default']) : '' ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
