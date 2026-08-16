<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

enum PrintJobState: string
{
    case Prepared = 'prepared';
    case Submitting = 'submitting';
    case Accepted = 'accepted';
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
    case Indeterminate = 'indeterminate';
}
