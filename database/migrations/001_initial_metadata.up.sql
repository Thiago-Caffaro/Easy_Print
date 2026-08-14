CREATE TABLE print_jobs (
    id TEXT PRIMARY KEY,
    correlation_id TEXT NOT NULL UNIQUE,
    cups_server_key TEXT NOT NULL,
    queue_name TEXT NOT NULL,
    cups_job_id INTEGER,
    original_name TEXT,
    detected_media_type TEXT NOT NULL CHECK (detected_media_type IN ('application/pdf', 'image/png', 'image/jpeg')),
    byte_size INTEGER NOT NULL CHECK (byte_size >= 0),
    copies INTEGER NOT NULL DEFAULT 1 CHECK (copies BETWEEN 1 AND 999),
    page_range TEXT,
    options_json TEXT NOT NULL DEFAULT '{}',
    state TEXT NOT NULL CHECK (state IN ('prepared', 'submitting', 'accepted', 'pending', 'processing', 'completed', 'cancelled', 'failed', 'indeterminate')),
    safe_error_code TEXT,
    safe_error_detail TEXT CHECK (safe_error_detail IS NULL OR length(safe_error_detail) <= 2048),
    submitted_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    finished_at TEXT,
    last_reconciled_at TEXT,
    retained_until TEXT NOT NULL,
    CHECK (json_valid(options_json)),
    CHECK (cups_job_id IS NULL OR cups_job_id > 0)
) STRICT;

CREATE INDEX print_jobs_recent_history_idx
    ON print_jobs (submitted_at DESC);

CREATE INDEX print_jobs_cups_reconciliation_idx
    ON print_jobs (cups_server_key, cups_job_id, state)
    WHERE finished_at IS NULL;

CREATE INDEX print_jobs_retention_idx
    ON print_jobs (retained_until);

CREATE TABLE job_events (
    id TEXT PRIMARY KEY,
    print_job_id TEXT NOT NULL,
    state TEXT NOT NULL,
    safe_reason_code TEXT,
    source TEXT NOT NULL CHECK (source IN ('application', 'cups', 'cleanup')),
    observed_at TEXT NOT NULL,
    FOREIGN KEY (print_job_id) REFERENCES print_jobs (id) ON DELETE CASCADE
) STRICT;

CREATE INDEX job_events_job_timeline_idx
    ON job_events (print_job_id, observed_at);

CREATE TABLE operational_errors (
    id TEXT PRIMARY KEY,
    correlation_id TEXT,
    stable_code TEXT NOT NULL,
    operation TEXT NOT NULL,
    safe_context_json TEXT NOT NULL DEFAULT '{}',
    bounded_diagnostic TEXT CHECK (bounded_diagnostic IS NULL OR length(bounded_diagnostic) <= 2048),
    occurred_at TEXT NOT NULL,
    retained_until TEXT NOT NULL,
    CHECK (json_valid(safe_context_json))
) STRICT;

CREATE INDEX operational_errors_recent_idx
    ON operational_errors (occurred_at DESC);

CREATE INDEX operational_errors_retention_idx
    ON operational_errors (retained_until);
