# SQLite metadata database

Easy Print owns one SQLite database in the web container. CUPS remains authoritative for queue configuration and active job state.

## Migration commands

```bash
php bin/migrate.php
php bin/rollback-migration.php
```

Migrations use paired `*.up.sql` and `*.down.sql` files. Every migration and rollback runs in a transaction and records its version only after the SQL succeeds. The container entrypoint applies pending migrations before starting the HTTP process.

## Initial schema

- `print_jobs` stores queue identity, detected media metadata, selected options, the CUPS job identifier, normalized state, timestamps, and bounded safe errors.
- `job_events` stores append-only normalized lifecycle observations when reconciliation needs a timeline.
- `operational_errors` stores stable codes, safe bounded context, diagnostics, and retention deadlines.
- `capability_snapshots` stores bounded, validated CUPS option metadata for the short-lived capability cache.
- `print_submission_keys` maps an opaque browser idempotency key to one print job to prevent duplicate `lp` calls.
- `schema_migrations` records applied versions.

The schema deliberately has no document blob, recoverable upload path, user account, credential, or CUPS spool data. JSON columns are validated by SQLite, and indexes cover recent history, unfinished CUPS reconciliation, and retention cleanup.

Capability snapshots are disposable operational cache entries, partitioned by the configured CUPS server key and opaque queue name. They contain option identifiers, driver labels, choices, defaults, categories, and a fingerprint, never document data. They are safe to omit from backups and are replaced after their short TTL when CUPS advertises a changed driver/capability fingerprint.

Submission keys are created transactionally with their job metadata row. Their foreign key uses cascade deletion, so history retention removes the idempotency record with the corresponding job. They are not credentials, CSRF tokens, document identifiers, or a source for reprinting.

New option metadata uses the versioned JSON shape `{"version":1,"values":{"Option":"Value"}}`. The history reader also accepts the original flat shape so existing development databases remain readable. Technical CUPS identifiers are displayed only as metadata and are always HTML-escaped.

## Retention fields

Every job and operational error receives a `retained_until` value when it is created. Bounded cleanup deletes expired print jobs and relies on foreign-key cascades to remove their idempotency keys and event timelines. Scheduling and orphaned-upload cleanup remain part of the production-hardening slice.

WAL mode is not enabled by default because network-backed and container-mounted filesystems vary. It can be evaluated only with deployment-specific evidence and an architecture decision.

See [SQLite backup and recovery](database-recovery.md) for quiescent Docker backup commands, migration failure handling, restore verification, retention boundaries, and TrueNAS SCALE dataset guidance.
