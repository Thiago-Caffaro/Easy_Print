# Print history

Easy Print retains metadata for recent print attempts, never the submitted document or a recoverable file path. The `/history` page is server-rendered, localized, keyboard accessible, and ordered by the newest submission first.

## Stored and displayed metadata

- sanitized original title, detected MIME type, and byte size;
- queue and CUPS job identifier when one was assigned;
- copies, page range, and a versioned map of validated CUPS options;
- normalized lifecycle state and timestamps;
- stable safe error code when a submission or later observation failed.

The page deliberately provides no download, preview, or reprint action. Technical diagnostics and local filesystem paths are not rendered.

## Pagination and failure behavior

History uses fixed pages of 20 entries and caps database queries at 50 records. Invalid page input falls back to page one, and requests beyond the final page resolve to the final available page. Database failures produce a localized unavailable state while a bounded structured warning is written to logs.

## Reconciliation and retention

CUPS observations may move non-final records through pending, processing, indeterminate, completed, or cancelled states. Completed, cancelled, and failed records are terminal and cannot be overwritten by a later stale observation. Each accepted transition appends a CUPS-sourced event.

Retention cleanup deletes a bounded number of expired rows per call. Related submission keys and lifecycle events are removed by SQLite foreign-key cascades. Cleanup never makes a document recoverable because document bytes are deleted immediately after submission handling.
