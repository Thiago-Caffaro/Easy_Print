<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

final readonly class CancelJobResult
{
    public function __construct(
        public CancelJobStatus $status,
        public ?string $diagnostic = null,
    ) {}
}
