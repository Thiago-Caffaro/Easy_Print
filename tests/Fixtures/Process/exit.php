<?php

declare(strict_types=1);

fwrite(STDERR, (string) ($argv[2] ?? 'failure'));
exit((int) ($argv[1] ?? 1));
