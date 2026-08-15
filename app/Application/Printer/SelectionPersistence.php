<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

enum SelectionPersistence: string
{
    case Clear = 'clear';
    case Keep = 'keep';
    case Store = 'store';
}
