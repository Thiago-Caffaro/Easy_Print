<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Cups;

use EasyPrint\Domain\Printer\PrinterState;

use function preg_match;
use function preg_split;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

final class LpstatPrinterStatusParser
{
    /** @return array{PrinterState,list<string>} */
    public function status(string $output, string $queueIdentifier): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($output));

        if (false === $lines || [] === $lines) {
            throw new MalformedLpstatOutput('The printer status response was empty.');
        }

        $prefix = 'printer ' . $queueIdentifier . ' ';

        if (!str_starts_with($lines[0], $prefix)) {
            throw new MalformedLpstatOutput('The printer status did not match the requested queue.');
        }

        $stateText = substr($lines[0], strlen($prefix));
        $state = match (true) {
            str_starts_with($stateText, 'is idle.') => PrinterState::Ready,
            str_starts_with($stateText, 'now printing ') => PrinterState::Processing,
            str_starts_with($stateText, 'disabled since ') => PrinterState::Stopped,
            default => PrinterState::Unknown,
        };
        $reasons = [];

        foreach ($lines as $line) {
            if (1 !== preg_match('/^\s*Alerts:\s*(?<reasons>.*)$/D', $line, $matches)) {
                continue;
            }

            $value = trim($matches['reasons']);

            if ('' === $value || 'none' === $value) {
                break;
            }

            $keywords = preg_split('/\s+/', $value);

            if (false === $keywords || count($keywords) > 20) {
                throw new MalformedLpstatOutput('The printer reasons were malformed or excessive.');
            }

            foreach ($keywords as $keyword) {
                if (1 !== preg_match('/^[a-z0-9][a-z0-9.-]{0,127}$/D', $keyword)) {
                    throw new MalformedLpstatOutput('A printer reason was unsafe.');
                }

                $reasons[$keyword] = true;
            }

            break;
        }

        return [$state, array_keys($reasons)];
    }

    public function accepting(string $output, string $queueIdentifier): bool
    {
        $line = trim($output);

        if (str_starts_with($line, $queueIdentifier . ' accepting requests since ')) {
            return true;
        }

        if (str_starts_with($line, $queueIdentifier . ' not accepting requests since ')) {
            return false;
        }

        throw new MalformedLpstatOutput('The accepting response was not recognized.');
    }
}
