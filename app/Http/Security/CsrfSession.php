<?php

declare(strict_types=1);

namespace EasyPrint\Http\Security;

final readonly class CsrfSession
{
    public function __construct(
        public string $cookie,
        public string $token,
        public bool $new,
    ) {}
}
