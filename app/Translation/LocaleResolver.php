<?php

declare(strict_types=1);

namespace EasyPrint\Translation;

use function in_array;
use function is_string;

use Psr\Http\Message\ServerRequestInterface;

final readonly class LocaleResolver
{
    /**
     * @param list<string> $enabledLocales
     */
    public function __construct(
        private string $defaultLocale,
        private array $enabledLocales,
    ) {}

    public function resolve(ServerRequestInterface $request): string
    {
        $requested = $request->getQueryParams()['lang'] ?? null;

        if (is_string($requested) && in_array($requested, $this->enabledLocales, true)) {
            return $requested;
        }

        return $this->defaultLocale;
    }
}
