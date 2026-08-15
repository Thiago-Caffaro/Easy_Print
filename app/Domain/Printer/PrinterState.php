<?php

declare(strict_types=1);

namespace EasyPrint\Domain\Printer;

enum PrinterState: string
{
    case Processing = 'processing';
    case Ready = 'ready';
    case Stopped = 'stopped';
    case Unavailable = 'unavailable';
    case Unknown = 'unknown';
}
