<?php

declare(strict_types=1);

namespace EasyPrint\Application\Printer;

interface CupsJobSubmitter
{
    public function submit(ValidatedPrintArguments $arguments, PrintDocument $document): CupsJobSubmission;
}
