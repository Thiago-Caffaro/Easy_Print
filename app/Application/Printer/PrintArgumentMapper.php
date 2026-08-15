<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

use function array_key_exists;
use function count;

use EasyPrint\Domain\Printer\CapabilityChoice;
use EasyPrint\Domain\Printer\CapabilityOption;
use EasyPrint\Domain\Printer\CapabilitySnapshot;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Domain\Printer\QueueSnapshot;

use function explode;
use function in_array;
use function is_string;
use function preg_match;

final class PrintArgumentMapper
{
    private const MAXIMUM_PAGE = 999_999;
    private const MAXIMUM_PAGE_SEGMENTS = 100;

    public function map(
        QueueSnapshot $queues,
        CapabilitySnapshot $capabilities,
        PrintRequestInput $input,
    ): PrintArgumentResult {
        if (CupsConnectivity::Available !== $queues->connectivity) {
            return PrintArgumentResult::rejected(PrintRequestFailure::QueueUnavailable);
        }

        if (!$queues->contains($input->queueIdentifier)) {
            return PrintArgumentResult::rejected(PrintRequestFailure::QueueChanged);
        }

        if (CupsConnectivity::Available !== $capabilities->connectivity) {
            return PrintArgumentResult::rejected(PrintRequestFailure::CapabilitiesUnavailable);
        }

        if ($capabilities->queueIdentifier !== $input->queueIdentifier
            || $capabilities->fingerprint !== $input->capabilityFingerprint) {
            return PrintArgumentResult::rejected(PrintRequestFailure::StaleCapabilities);
        }

        $copies = $this->copies($input->copies);

        if (null === $copies) {
            return PrintArgumentResult::rejected(PrintRequestFailure::InvalidCopies);
        }

        $pageRange = $this->pageRange($input->pageRange);

        if (false === $pageRange) {
            return PrintArgumentResult::rejected(PrintRequestFailure::InvalidPageRange);
        }

        $selectedOptions = $this->selectedOptions($capabilities->options, $input->options);

        if (null === $selectedOptions) {
            return PrintArgumentResult::rejected(PrintRequestFailure::InvalidOption);
        }

        $arguments = ['-d', $input->queueIdentifier, '-n', (string) $copies];

        if (null !== $pageRange) {
            $arguments[] = '-P';
            $arguments[] = $pageRange;
        }

        foreach ($selectedOptions as $name => $value) {
            $arguments[] = '-o';
            $arguments[] = $name . '=' . $value;
        }

        return PrintArgumentResult::accepted(new ValidatedPrintArguments(
            queueIdentifier: $input->queueIdentifier,
            copies: $copies,
            pageRange: $pageRange,
            selectedOptions: $selectedOptions,
            arguments: $arguments,
        ));
    }

    private function copies(string $copies): ?int
    {
        if (1 !== preg_match('/^[1-9][0-9]{0,2}$/D', $copies)) {
            return null;
        }

        $value = (int) $copies;

        return $value <= 999 ? $value : null;
    }

    private function pageRange(?string $pageRange): string|false|null
    {
        if (null === $pageRange || '' === $pageRange) {
            return null;
        }

        if (strlen($pageRange) > 512) {
            return false;
        }

        $segments = explode(',', $pageRange);

        if (count($segments) > self::MAXIMUM_PAGE_SEGMENTS) {
            return false;
        }

        foreach ($segments as $segment) {
            if (1 !== preg_match('/^(?<first>[1-9][0-9]{0,5})(?:-(?<last>[1-9][0-9]{0,5}))?$/D', $segment, $matches)) {
                return false;
            }

            $first = (int) $matches['first'];
            $last = '' !== ($matches['last'] ?? '') ? (int) $matches['last'] : $first;

            if ($first > self::MAXIMUM_PAGE || $last > self::MAXIMUM_PAGE || $first > $last) {
                return false;
            }
        }

        return $pageRange;
    }

    /**
     * @param list<CapabilityOption> $capabilities
     * @param array<string,mixed>    $requested
     *
     * @return null|array<string,string>
     */
    private function selectedOptions(array $capabilities, array $requested): ?array
    {
        if (count($requested) > count($capabilities)) {
            return null;
        }

        $remaining = $requested;
        $selected = [];

        foreach ($capabilities as $option) {
            if (!array_key_exists($option->technicalIdentifier, $remaining)) {
                continue;
            }

            $value = $remaining[$option->technicalIdentifier];

            if (!$option->isRenderable() || !is_string($value) || !$this->supports($option, $value)) {
                return null;
            }

            $selected[$option->technicalIdentifier] = $value;
            unset($remaining[$option->technicalIdentifier]);
        }

        return [] === $remaining ? $selected : null;
    }

    private function supports(CapabilityOption $option, string $requested): bool
    {
        return in_array($requested, array_map(
            static fn(CapabilityChoice $choice): string => $choice->technicalIdentifier,
            $option->choices,
        ), true);
    }
}
