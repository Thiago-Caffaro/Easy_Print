<?php

declare(strict_types=1);

namespace EasyPrint\Domain\Printer;

enum CapabilityCategory: string
{
    case MediaSize = 'media_size';
    case MediaType = 'media_type';
    case ColorMode = 'color_mode';
    case Quality = 'quality';
    case Resolution = 'resolution';
    case Orientation = 'orientation';
    case Sides = 'sides';
    case MediaSource = 'media_source';
    case Scaling = 'scaling';
    case Unknown = 'unknown';
}
