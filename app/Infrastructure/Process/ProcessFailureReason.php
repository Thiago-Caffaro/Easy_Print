<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Process;

enum ProcessFailureReason: string
{
    case InvalidArgument = 'invalid_argument';
    case NonZeroExit = 'non_zero_exit';
    case NotAllowed = 'not_allowed';
    case OutputLimit = 'output_limit';
    case StartFailed = 'start_failed';
    case TimedOut = 'timed_out';
}
