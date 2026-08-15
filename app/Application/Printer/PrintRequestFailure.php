<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

enum PrintRequestFailure: string
{
    case QueueUnavailable = 'queue_unavailable';
    case QueueChanged = 'queue_changed';
    case CapabilitiesUnavailable = 'capabilities_unavailable';
    case StaleCapabilities = 'stale_capabilities';
    case InvalidCopies = 'invalid_copies';
    case InvalidPageRange = 'invalid_page_range';
    case InvalidOption = 'invalid_option';

    public function shouldRefreshForm(): bool
    {
        return match ($this) {
            self::QueueChanged, self::StaleCapabilities => true,
            default => false,
        };
    }
}
