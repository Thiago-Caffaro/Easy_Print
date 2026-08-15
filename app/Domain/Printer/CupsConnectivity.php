<?php

declare(strict_types=1);

namespace EasyPrint\Domain\Printer;

enum CupsConnectivity: string
{
    case Available = 'available';
    case MalformedResponse = 'malformed_response';
    case TimedOut = 'timed_out';
    case Unauthorized = 'unauthorized';
    case Unavailable = 'unavailable';
}
