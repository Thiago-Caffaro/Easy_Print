# Error taxonomy

Easy Print separates technical failure categories from localized browser copy. Stable codes are suitable for metadata and bounded diagnostics; raw command output, stack traces, paths, addresses, filenames, and secrets are never rendered or persisted.

| Boundary | Stable examples | Browser behavior | Diagnostic behavior |
| --- | --- | --- | --- |
| Configuration | invalid environment, unsupported locale, invalid path | Container fails readiness/startup with a generic operator-facing error | Startup log identifies the setting name, never its secret value |
| HTTP validation | `request_invalid`, invalid CSRF, body/header limit | `403`, `413`, or `422` with a localized retry/action message | Correlation ID and bounded request metadata |
| Queue/CUPS connectivity | `unavailable`, `timed_out`, `unauthorized`, `malformed_response` | Shows dependency state and avoids pretending an empty queue is healthy | Structured event records connectivity category and operation |
| Print arguments | `queue_unavailable`, `capabilities_unavailable`, `stale_capabilities`, `invalid_copies`, `invalid_page_range`, `invalid_option` | Refreshes the form or asks for a valid value | No browser value is passed to a process without allowlist validation |
| Upload/storage | `missing`, `too_large`, `invalid_extension`, `mime_mismatch`, `invalid_pdf`, `invalid_image`, `storage_unavailable`, `upload_failed`, `temporary_file_cleanup_failed` | Localized, format-specific guidance | Aggregate cleanup counters and safe category only |
| Submission | `cups_response_unrecognized`, `cups_submission_timeout`, `cups_response_too_large`, `cups_rejected_submission`, `cups_process_unavailable`, `submission_configuration_error` | Accepted, pending, or failed result without an unsafe automatic retry | Numeric exit/duration category only; CUPS output is discarded |
| Cancellation | `not_found`, `not_cancelable`, `unavailable`, `failed` | Redirects to the active-job page with a localized status | Queue and numeric job ID are bounded; command output is not retained |
| Persistence | database unavailable/read failure, migration failure | Readiness/history fallback remains explicit; no partial success is reported | Logger records component, operation, and correlation ID |

The Portuguese and English catalogs are required to contain the same keys. Tests cover catalog parity, upload failure mappings, CUPS outcome mappings, safe HTTP rendering, and correlation-aware structured logs.
