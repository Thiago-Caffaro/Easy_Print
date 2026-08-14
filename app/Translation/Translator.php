<?php

declare(strict_types=1);

namespace EasyPrint\Translation;

use function array_key_exists;
use function is_array;
use function is_file;
use function is_string;
use function sprintf;
use function strtr;

final readonly class Translator
{
    /**
     * @param array<string, array<string,string>> $catalogs
     */
    private function __construct(
        private array $catalogs,
        private string $fallbackLocale,
    ) {}

    /**
     * @param list<string> $locales
     */
    public static function fromDirectory(string $directory, array $locales, string $fallbackLocale): self
    {
        $catalogs = [];

        foreach ($locales as $locale) {
            $path = $directory . '/' . $locale . '.php';

            if (!is_file($path)) {
                throw new TranslationException(sprintf('The translation catalog for %s is missing.', $locale));
            }

            $catalog = require $path;

            if (!is_array($catalog)) {
                throw new TranslationException(sprintf('The translation catalog for %s is invalid.', $locale));
            }

            foreach ($catalog as $key => $message) {
                if (!is_string($key) || !is_string($message)) {
                    throw new TranslationException(sprintf('The translation catalog for %s must contain string keys and messages.', $locale));
                }
            }

            /** @var array<string,string> $catalog */
            $catalogs[$locale] = $catalog;
        }

        if (!array_key_exists($fallbackLocale, $catalogs)) {
            throw new TranslationException('The fallback translation catalog is not enabled.');
        }

        $reference = $catalogs[$fallbackLocale];

        foreach ($catalogs as $locale => $catalog) {
            $difference = CatalogValidator::compare($reference, $catalog);

            if ([] !== $difference['missing'] || [] !== $difference['orphaned']) {
                throw new TranslationException(sprintf('The translation catalog for %s does not match the fallback catalog.', $locale));
            }
        }

        return new self($catalogs, $fallbackLocale);
    }

    /**
     * @param array<string, scalar> $parameters
     */
    public function translate(string $locale, string $key, array $parameters = []): string
    {
        $catalog = $this->catalogs[$locale] ?? $this->catalogs[$this->fallbackLocale];
        $message = $catalog[$key] ?? $this->catalogs[$this->fallbackLocale][$key] ?? null;

        if (null === $message) {
            throw new TranslationException(sprintf('Unknown translation key: %s.', $key));
        }

        $replace = [];

        foreach ($parameters as $name => $value) {
            $replace['{' . $name . '}'] = (string) $value;
        }

        return strtr($message, $replace);
    }

    /**
     * @return array<string,string>
     */
    public function catalog(string $locale): array
    {
        return $this->catalogs[$locale] ?? $this->catalogs[$this->fallbackLocale];
    }
}
