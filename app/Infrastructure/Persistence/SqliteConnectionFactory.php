<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Persistence;

use PDO;

final class SqliteConnectionFactory
{
    public static function create(string $databasePath): PDO
    {
        $connection = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $connection->exec('PRAGMA foreign_keys = ON');
        $connection->exec('PRAGMA busy_timeout = 5000');

        return $connection;
    }
}
