# ADR 0003: Store metadata-only print history

- Status: Accepted
- Date: 2026-08-14

## Context

Retaining uploaded documents enables reprinting but increases privacy, storage, backup, and access-control obligations. v1.0 has no application users or login and runs on a trusted private network.

## Decision

Delete uploaded PDF, PNG, and JPEG files after CUPS accepts them into the spool. Persist only the metadata required for history, diagnostics, and reconciliation, including queue, sanitized display name, file type and size, selected options, CUPS job identifier, timestamps, status, and sanitized errors.

## Consequences

- Easy Print cannot reprint from history.
- Storage and privacy risks are reduced.
- Cleanup must cover rejected, timed-out, and abandoned uploads.
- Logs and database fields require explicit size and content limits.
