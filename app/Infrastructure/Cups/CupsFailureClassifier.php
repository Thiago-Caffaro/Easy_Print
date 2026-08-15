<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Cups;

use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Infrastructure\Process\ProcessFailureReason;
use EasyPrint\Infrastructure\Process\ProcessResult;

use function str_contains;
use function strtolower;

final class CupsFailureClassifier
{
    public static function classify(ProcessResult $result): CupsConnectivity
    {
        if (ProcessFailureReason::TimedOut === $result->failureReason) {
            return CupsConnectivity::TimedOut;
        }

        if (ProcessFailureReason::OutputLimit === $result->failureReason) {
            return CupsConnectivity::MalformedResponse;
        }

        $diagnostic = strtolower($result->stderr . "\n" . $result->stdout);

        foreach (['not authorized', 'unauthorized', 'forbidden', 'client-error-not-authorized'] as $marker) {
            if (str_contains($diagnostic, $marker)) {
                return CupsConnectivity::Unauthorized;
            }
        }

        return CupsConnectivity::Unavailable;
    }
}
