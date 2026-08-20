# Testing strategy

Normal CI and local tests never contact a physical printer. Each CUPS boundary receives a fake bounded process runner or a sanitized synthetic fixture, so malformed output, timeouts, unavailable services, stale capabilities, and safe command arguments are deterministic.

## Current coverage

- Queue discovery, queue selection, capability parsing/cache, and printer-state mapping.
- PDF and PNG/JPEG validation, MIME/structure checks, private storage containment, and orphan cleanup.
- Print argument validation and CUPS submission outcome mapping, including idempotency and metadata-only history.
- Active-job polling, localized rendering, CSRF-protected cancellation, and post-cancel reconciliation.
- Print history pagination, retention, localized dates/file sizes, health endpoints, security headers, request limits, and correlation IDs.
- Sanitized contract fixtures for empty/multiple queues, capability categories, active jobs, and accepted submission output.

Run the complete suite with:

```bash
composer test
composer analyse
composer format:check
npx --yes markdownlint-cli2@0.23.2
```

The physical Epson L4150 matrix remains an opt-in compatibility test. It must record the CUPS version, queue alias, sanitized capabilities, observed job lifecycle, and cleanup result without committing private addresses, filenames, or document content. A future browser-level harness should compose these existing fakes into one upload-to-history scenario; until then, the vertical slices remain intentionally isolated to keep failures diagnosable.
