<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

interface PrintHistoryReader
{
    public function readPage(int $page, int $perPage): PrintHistoryPage;
}
