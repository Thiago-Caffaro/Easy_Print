<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

enum SelectionSource: string
{
    case DefaultQueue = 'default_queue';
    case FirstAvailable = 'first_available';
    case None = 'none';
    case Persisted = 'persisted';
    case Requested = 'requested';
}
