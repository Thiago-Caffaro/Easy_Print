<?php

declare(strict_types=1);

namespace EasyPrint\Domain\Printer;

enum ActiveJobState: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Unknown = 'unknown';
}
