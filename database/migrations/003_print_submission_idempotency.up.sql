CREATE TABLE print_submission_keys (
    submission_key TEXT PRIMARY KEY CHECK (length(submission_key) BETWEEN 32 AND 128),
    print_job_id TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL,
    FOREIGN KEY (print_job_id) REFERENCES print_jobs (id) ON DELETE CASCADE
) STRICT;
