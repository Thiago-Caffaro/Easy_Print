<?php

declare(strict_types=1);

namespace EasyPrint\Translation;

use function array_diff;
use function array_keys;
use function array_values;
use function sort;

final class CatalogValidator
{
    /**
     * @param array<string,string> $reference
     * @param array<string,string> $candidate
     *
     * @return array{missing:list<string>,orphaned:list<string>}
     */
    public static function compare(array $reference, array $candidate): array
    {
        $missing = array_values(array_diff(array_keys($reference), array_keys($candidate)));
        $orphaned = array_values(array_diff(array_keys($candidate), array_keys($reference)));
        sort($missing);
        sort($orphaned);

        return ['missing' => $missing, 'orphaned' => $orphaned];
    }
}
