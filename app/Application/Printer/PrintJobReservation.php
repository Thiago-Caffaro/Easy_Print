<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

final readonly class PrintJobReservation
{
    public function __construct(
        public PrintJobRecord $record,
        public bool $created,
    ) {}
}
