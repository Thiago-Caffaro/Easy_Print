CREATE TABLE capability_snapshots (
    cups_server_key TEXT NOT NULL CHECK (length(cups_server_key) BETWEEN 1 AND 64),
    queue_name TEXT NOT NULL CHECK (length(queue_name) BETWEEN 1 AND 127),
    fingerprint TEXT NOT NULL CHECK (length(fingerprint) = 64),
    payload_json TEXT NOT NULL CHECK (json_valid(payload_json) AND length(payload_json) <= 1048576),
    cached_at INTEGER NOT NULL CHECK (cached_at >= 0),
    expires_at INTEGER NOT NULL CHECK (expires_at > cached_at),
    PRIMARY KEY (cups_server_key, queue_name)
) STRICT;

CREATE INDEX capability_snapshots_expiry_idx
    ON capability_snapshots (expires_at);
