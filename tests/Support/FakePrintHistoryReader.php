<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Support;

use EasyPrint\Application\Printer\PrintHistoryPage;
use EasyPrint\Application\Printer\PrintHistoryReader;

final readonly class FakePrintHistoryReader implements PrintHistoryReader
{
    public function __construct(private PrintHistoryPage $result) {}

    public function readPage(int $page, int $perPage): PrintHistoryPage
    {
        return $this->result;
    }
}
