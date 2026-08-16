<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

interface PrintJobRepository
{
    public function reserve(string $submissionKey, PrintJobDraft $draft): PrintJobReservation;

    public function markSubmitting(string $printJobId, string $updatedAt): PrintJobRecord;

    public function finishSubmission(
        string $printJobId,
        CupsJobSubmission $submission,
        bool $temporaryFileDeleted,
        string $updatedAt,
    ): PrintJobRecord;
}
