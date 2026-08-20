<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

enum CancelJobStatus: string
{
    case Cancelled = 'cancelled';
    case NotFound = 'not_found';
    case NotCancelable = 'not_cancelable';
    case Unavailable = 'unavailable';
    case Failed = 'failed';
}
