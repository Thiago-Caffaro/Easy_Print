<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Observability;

use LogicException;

final class CorrelationContext
{
    private ?string $correlationId = null;

    public function begin(string $correlationId): void
    {
        if (null !== $this->correlationId) {
            throw new LogicException('A correlation scope is already active.');
        }

        $this->correlationId = $correlationId;
    }

    public function current(): ?string
    {
        return $this->correlationId;
    }

    public function end(string $correlationId): void
    {
        if ($correlationId !== $this->correlationId) {
            throw new LogicException('The active correlation scope does not match.');
        }

        $this->correlationId = null;
    }
}
