<?php

declare(strict_types=1);

namespace EasyPrint\Infrastructure\Persistence;

use EasyPrint\Application\Printer\CapabilitySnapshotCache;
use EasyPrint\Domain\Printer\CapabilitySnapshot;

use function is_array;
use function is_int;
use function is_string;

use PDO;
use Throwable;
use UnexpectedValueException;

final readonly class SqliteCapabilitySnapshotCache implements CapabilitySnapshotCache
{
    public function __construct(
        private PDO $connection,
        private CapabilitySnapshotCodec $codec,
    ) {}

    public function find(string $serverKey, string $queueIdentifier, int $now): ?CapabilitySnapshot
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT fingerprint, payload_json, expires_at
                FROM capability_snapshots
                WHERE cups_server_key = :server_key AND queue_name = :queue_name
                SQL,
        );
        $statement->execute([
            'server_key' => $serverKey,
            'queue_name' => $queueIdentifier,
        ]);
        $record = $statement->fetch();
        $statement->closeCursor();

        if (false === $record) {
            return null;
        }

        if (!is_array($record)
            || !is_string($record['fingerprint'] ?? null)
            || !is_string($record['payload_json'] ?? null)
            || !is_int($record['expires_at'] ?? null)) {
            $this->invalidate($serverKey, $queueIdentifier);

            return null;
        }

        if ((int) $record['expires_at'] <= $now) {
            $this->invalidate($serverKey, $queueIdentifier);

            return null;
        }

        try {
            return $this->codec->decode(
                $queueIdentifier,
                (string) $record['fingerprint'],
                (string) $record['payload_json'],
            );
        } catch (Throwable) {
            $this->invalidate($serverKey, $queueIdentifier);

            return null;
        }
    }

    public function save(string $serverKey, CapabilitySnapshot $snapshot, int $cachedAt, int $expiresAt): void
    {
        $payload = $this->codec->encode($snapshot);

        if (null === $snapshot->fingerprint) {
            throw new UnexpectedValueException('A cached capability snapshot requires a fingerprint.');
        }

        $prune = $this->connection->prepare('DELETE FROM capability_snapshots WHERE expires_at <= :now');
        $prune->execute(['now' => $cachedAt]);

        $statement = $this->connection->prepare(
            <<<'SQL'
                INSERT INTO capability_snapshots (
                    cups_server_key,
                    queue_name,
                    fingerprint,
                    payload_json,
                    cached_at,
                    expires_at
                ) VALUES (
                    :server_key,
                    :queue_name,
                    :fingerprint,
                    :payload_json,
                    :cached_at,
                    :expires_at
                )
                ON CONFLICT (cups_server_key, queue_name) DO UPDATE SET
                    fingerprint = excluded.fingerprint,
                    payload_json = excluded.payload_json,
                    cached_at = excluded.cached_at,
                    expires_at = excluded.expires_at
                SQL,
        );
        $statement->execute([
            'server_key' => $serverKey,
            'queue_name' => $snapshot->queueIdentifier,
            'fingerprint' => $snapshot->fingerprint,
            'payload_json' => $payload,
            'cached_at' => $cachedAt,
            'expires_at' => $expiresAt,
        ]);
    }

    public function invalidate(string $serverKey, string $queueIdentifier): void
    {
        $statement = $this->connection->prepare(
            'DELETE FROM capability_snapshots WHERE cups_server_key = :server_key AND queue_name = :queue_name',
        );
        $statement->execute([
            'server_key' => $serverKey,
            'queue_name' => $queueIdentifier,
        ]);
    }
}
