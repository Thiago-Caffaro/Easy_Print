<?php

declare(strict_types=1);

namespace EasyPrint\Application\Health;

enum HealthStatus: string
{
    case Ok = 'ok';
    case Degraded = 'degraded';
    case Unavailable = 'unavailable';
}
