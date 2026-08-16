# Print job submission

Easy Print submits one already-validated private document to one current CUPS queue. The application remains a client of CUPS: it does not spool independently, retain document bytes, or infer printer features.

## Application contract

Submission receives four trusted application outputs:

- a document produced by the secure PDF or image upload boundary;
- a `ValidatedPrintArguments` value produced from the current queue and capability snapshots;
- the configured opaque CUPS server key; and
- a browser submission key generated with at least 128 bits of randomness.

The use case reserves a metadata row before invoking CUPS, moves it through `prepared` and `submitting`, records the terminal submission outcome, and appends each transition to `job_events`. No document path or document bytes enter SQLite.

## CUPS command boundary

The adapter calls the allowlisted `lp` executable through the bounded process runner. It builds a separated argument list in this order:

```text
[-E] -h <configured-host:port> <validated-arguments> -- <private-absolute-path>
```

`-E` is present only when CUPS encryption is configured as required. IPv6 hosts are bracketed before the port is appended. The `--` terminator prevents a random filename from becoming an option. The adapter never builds a shell command and never re-parses browser fields.

The process environment fixes `LANG` and `LC_ALL` to `C`. A successful one-file response must exactly identify the expected queue and a positive integer job identifier, for example:

```text
request id is REFERENCE_QUEUE-321 (1 file(s))
```

Unexpected successful output is not treated as acceptance because Easy Print cannot safely associate it with a CUPS job.

## Outcome mapping

| Process result | Local state | Stable code | Automatic retry |
| --- | --- | --- | --- |
| Exit 0 and valid expected job ID | `accepted` | none | No |
| Exit 0 with unrecognized output | `indeterminate` | `cups_response_unrecognized` | No |
| Timeout | `indeterminate` | `cups_submission_timeout` | No |
| Output limit reached | `indeterminate` | `cups_response_too_large` | No |
| Non-zero exit | `failed` | `cups_rejected_submission` | No |
| Process start failure | `failed` | `cups_process_unavailable` | No |
| Runner policy/configuration rejection | `failed` | `submission_configuration_error` | No |

Timeouts and bounded-output failures are deliberately indeterminate: CUPS may have accepted the document before the client lost a reliable response. A later job-reconciliation slice may resolve those records. Easy Print must not resubmit them automatically.

Stored diagnostics contain only the failure category, numeric exit status when known, duration, and temporary-cleanup status. Standard output, standard error, server addresses, queue output, filenames, and paths are never persisted as diagnostics.

## Idempotency and cleanup

`print_submission_keys` maps one opaque browser key to one print job inside the same SQLite transaction that creates the metadata row. A repeated key returns the existing durable record without invoking `lp` again. Any newly staged duplicate upload is deleted.

The private temporary document is deleted after accepted, failed, indeterminate, duplicate, and invalid-key paths. This satisfies both privacy and duplicate-safety requirements: Easy Print cannot reprint from retained bytes. A failed unlink is recorded as a bounded cleanup diagnostic without changing a confirmed CUPS acceptance into a failure.

Submission keys are metadata, not authentication or CSRF tokens. The HTTP action must still require the existing CSRF control when it is introduced.

## Verification boundary

Normal tests use synthetic sanitized CUPS responses and a fake bounded runner. They verify argument separation, exact job-ID parsing, IPv6/encryption addressing, outcome mapping, metadata persistence, cleanup, and duplicate suppression without contacting CUPS.

A physical-printer test is opt-in and requires explicit operator authorization because it consumes paper or ink. Record the CUPS version, queue alias, sanitized response, observed job ID/state, and cleanup result after authorization. Do not commit private addresses, filenames, paths, usernames, or document contents.

Scheduled printing and reprinting retained files are intentionally out of scope.
