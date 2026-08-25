<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Cups;

use EasyPrint\Domain\Printer\CapabilityCategory;
use EasyPrint\Domain\Printer\CapabilityChoice;
use EasyPrint\Domain\Printer\CapabilityOption;

use function explode;
use function hash;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

use function preg_match;
use function preg_split;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

final class LpoptionsOutputParser
{
    private const MAXIMUM_OPTIONS = 128;

    /**
     * @return list<CapabilityOption>
     */
    public function parse(string $output): array
    {
        if (str_contains($output, "\0")) {
            throw new MalformedLpoptionsOutput('The lpoptions output contains a null byte.');
        }

        $options = [];
        $seen = [];

        foreach (explode("\n", str_replace("\r\n", "\n", $output)) as $line) {
            $line = trim($line);

            if ('' === $line) {
                continue;
            }

            if (count($options) >= self::MAXIMUM_OPTIONS) {
                throw new MalformedLpoptionsOutput('The lpoptions output contains too many options.');
            }

            if (1 !== preg_match('/^(?<name>[A-Za-z0-9][A-Za-z0-9_.-]{0,127})(?:\/(?<label>[^:]{1,256}))?:[ \t]*(?<choices>.*)$/D', $line, $matches)) {
                throw new MalformedLpoptionsOutput('The lpoptions output contains an invalid option line.');
            }

            $technicalIdentifier = $matches['name'];

            if (isset($seen[$technicalIdentifier])) {
                throw new MalformedLpoptionsOutput('The lpoptions output contains duplicate options.');
            }

            $seen[$technicalIdentifier] = true;
            $driverLabel = trim('' !== $matches['label'] ? $matches['label'] : $technicalIdentifier);

            if ('' === $driverLabel
                || 1 !== preg_match('//u', $driverLabel)
                || 1 === preg_match('/[\x00-\x1F\x7F]/', $driverLabel)) {
                throw new MalformedLpoptionsOutput('The lpoptions output contains an invalid driver label.');
            }

            $tokens = preg_split('/[ \t]+/', trim($matches['choices']));

            if (false === $tokens || [] === $tokens || [''] === $tokens || count($tokens) > 256) {
                throw new MalformedLpoptionsOutput('The lpoptions output contains an invalid choice list.');
            }

            $choices = [];
            $choiceIdentifiers = [];
            $default = null;

            foreach ($tokens as $token) {
                $isDefault = str_starts_with($token, '*');
                $identifier = $isDefault ? substr($token, 1) : $token;

                // CUPS drivers may expose signed numeric values (for example,
                // Brightness and Contrast commonly advertise -25..25).
                if (1 !== preg_match('/^-?[A-Za-z0-9][A-Za-z0-9_.:+\/-]{0,127}$/D', $identifier)
                    || isset($choiceIdentifiers[$identifier])) {
                    throw new MalformedLpoptionsOutput('The lpoptions output contains an invalid or duplicate choice.');
                }

                if ($isDefault && null !== $default) {
                    throw new MalformedLpoptionsOutput('The lpoptions output contains multiple defaults for one option.');
                }

                $choiceIdentifiers[$identifier] = true;
                $default = $isDefault ? $identifier : $default;
                $choices[] = new CapabilityChoice($identifier);
            }

            $options[] = new CapabilityOption(
                technicalIdentifier: $technicalIdentifier,
                driverLabel: $driverLabel,
                category: $this->category($technicalIdentifier),
                choices: $choices,
                defaultTechnicalIdentifier: $default,
            );
        }

        return $options;
    }

    /**
     * @param list<CapabilityOption> $options
     */
    public function fingerprint(array $options): string
    {
        $normalized = array_map(static fn(CapabilityOption $option): array => [
            'name' => $option->technicalIdentifier,
            'driver_label' => $option->driverLabel,
            'category' => $option->category->value,
            'default' => $option->defaultTechnicalIdentifier,
            'choices' => array_map(
                static fn(CapabilityChoice $choice): string => $choice->technicalIdentifier,
                $option->choices,
            ),
        ], $options);

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function category(string $technicalIdentifier): CapabilityCategory
    {
        return match (strtolower($technicalIdentifier)) {
            'pagesize', 'media' => CapabilityCategory::MediaSize,
            'mediatype', 'media-type' => CapabilityCategory::MediaType,
            'colormodel', 'colormode', 'print-color-mode' => CapabilityCategory::ColorMode,
            'printoutmode', 'outputmode', 'print-quality', 'cupsprintquality' => CapabilityCategory::Quality,
            'resolution', 'printer-resolution' => CapabilityCategory::Resolution,
            'orientation', 'orientation-requested' => CapabilityCategory::Orientation,
            'duplex', 'sides' => CapabilityCategory::Sides,
            'inputslot', 'media-source' => CapabilityCategory::MediaSource,
            'scaling', 'print-scaling', 'pagescaling', 'fit-to-page' => CapabilityCategory::Scaling,
            default => CapabilityCategory::Unknown,
        };
    }
}
