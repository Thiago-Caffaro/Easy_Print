<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Persistence;

use function count;

use EasyPrint\Domain\Printer\CapabilityCategory;
use EasyPrint\Domain\Printer\CapabilityChoice;
use EasyPrint\Domain\Printer\CapabilityOption;
use EasyPrint\Domain\Printer\CapabilitySnapshot;
use EasyPrint\Domain\Printer\CupsConnectivity;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

use Throwable;
use UnexpectedValueException;

final class CapabilitySnapshotCodec
{
    public function encode(CapabilitySnapshot $snapshot): string
    {
        if (CupsConnectivity::Available !== $snapshot->connectivity) {
            throw new UnexpectedValueException('Only available capability snapshots can be cached.');
        }

        return json_encode([
            'options' => array_map(static fn(CapabilityOption $option): array => [
                'technical_identifier' => $option->technicalIdentifier,
                'driver_label' => $option->driverLabel,
                'category' => $option->category->value,
                'default_technical_identifier' => $option->defaultTechnicalIdentifier,
                'choices' => array_map(
                    static fn(CapabilityChoice $choice): string => $choice->technicalIdentifier,
                    $option->choices,
                ),
            ], $snapshot->options),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public function decode(string $queueIdentifier, string $fingerprint, string $payload): CapabilitySnapshot
    {
        try {
            $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);

            if (!is_array($decoded) || !isset($decoded['options']) || !is_array($decoded['options'])) {
                throw new UnexpectedValueException('The cached capability payload has an invalid root.');
            }

            if (count($decoded['options']) > 128) {
                throw new UnexpectedValueException('The cached capability payload contains too many options.');
            }

            $options = [];

            foreach ($decoded['options'] as $encodedOption) {
                if (!is_array($encodedOption)
                    || !is_string($encodedOption['technical_identifier'] ?? null)
                    || !is_string($encodedOption['driver_label'] ?? null)
                    || !is_string($encodedOption['category'] ?? null)
                    || !is_array($encodedOption['choices'] ?? null)
                    || (null !== ($encodedOption['default_technical_identifier'] ?? null)
                        && !is_string($encodedOption['default_technical_identifier']))) {
                    throw new UnexpectedValueException('The cached capability payload contains an invalid option.');
                }

                $category = CapabilityCategory::tryFrom($encodedOption['category']);

                if (null === $category) {
                    throw new UnexpectedValueException('The cached capability payload contains an invalid category.');
                }

                if (count($encodedOption['choices']) > 256) {
                    throw new UnexpectedValueException('The cached capability payload contains too many choices.');
                }

                $choices = [];

                foreach ($encodedOption['choices'] as $choice) {
                    if (!is_string($choice)) {
                        throw new UnexpectedValueException('The cached capability payload contains an invalid choice.');
                    }

                    $choices[] = new CapabilityChoice($choice);
                }

                $options[] = new CapabilityOption(
                    technicalIdentifier: $encodedOption['technical_identifier'],
                    driverLabel: $encodedOption['driver_label'],
                    category: $category,
                    choices: $choices,
                    defaultTechnicalIdentifier: $encodedOption['default_technical_identifier'] ?? null,
                );
            }

            return new CapabilitySnapshot(
                queueIdentifier: $queueIdentifier,
                connectivity: CupsConnectivity::Available,
                options: $options,
                fingerprint: $fingerprint,
            );
        } catch (UnexpectedValueException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new UnexpectedValueException('The cached capability payload is invalid.', previous: $exception);
        }
    }
}
