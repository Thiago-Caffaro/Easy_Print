<?php

declare(strict_types=1);

$arguments = $_SERVER['argv'] ?? [];

echo json_encode(array_slice($arguments, 1), JSON_THROW_ON_ERROR);
