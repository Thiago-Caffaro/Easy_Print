# Active print jobs

Easy Print reads active jobs from the configured CUPS server without taking ownership of the spool. CUPS remains authoritative for which jobs exist and whether a queue is currently processing one.

## Read-only command contract

The adapter uses the bounded allowlisted process runner and the deterministic `C` locale:

```text
lpstat [-E] -h <configured-host:port> -W not-completed -o
lpstat [-E] -h <configured-host:port> -p
```

OpenPrinting documents that `-W not-completed` must precede `-o`, and that `-o` lists queued jobs. The current CUPS implementation requests job ID, name, owner, size, creation time, queue URI, messages, and reasons, but its normal output emits only the request identifier, owner, byte size, and creation timestamp. See the official [`lpstat` manual](https://openprinting.github.io/cups/cups-local/lpstat.html) and [`show_jobs` implementation](https://github.com/OpenPrinting/cups/blob/master/systemv/lpstat.c#L1218-L1444).

The parser therefore does not claim that a CUPS job title came from `lpstat`. It accepts at most 250 bounded rows and treats any malformed row as a malformed dependency response instead of displaying partial untrusted output.

The second command identifies the job currently printing on each queue. Jobs are normalized as:

- `processing` when the queue reports that exact request identifier as current;
- `pending` when the queue state is known and another job is current or the queue is idle/stopped; and
- `unknown` when the queue-state query fails or cannot identify the current job.

An unavailable, unauthorized, timed-out, or malformed job-list query is distinct from an available empty list. A failed secondary queue-state query keeps the active jobs visible with `unknown` state.

## Display metadata

For a job submitted through Easy Print, the fragment looks up the already-validated original filename by CUPS server key, queue identifier, and numeric job ID. The renderer HTML-escapes that value.

Jobs created by another CUPS client are still shown, using a localized neutral title such as “Job created outside Easy Print”. Easy Print does not expose the originating username and does not invent a document title that `lpstat` did not provide.

Displayed fields are the numeric CUPS job ID, opaque escaped queue identifier, bounded timestamp label, byte size, localized normalized state, and safe title.

## HTMX polling

The home page server-renders an accessible fallback link and loads the active-job fragment from `GET /jobs/active`. HTMX is pinned and served locally; private deployments do not load a CDN resource.

- while at least one job is active, the fragment polls every 3 seconds;
- after a CUPS error, it backs off to every 15 seconds;
- after an available empty result, automatic polling stops; and
- a normal link/HTMX refresh control remains available in every state.

The fragment uses `aria-live="polite"`, keeps a stable heading/region, and replaces itself with `outerHTML`. It performs no CUPS mutation and is not a cancellation endpoint.

## Verification boundary

Synthetic sanitized fixtures cover multiple printer-agnostic queues, processing and pending normalization, empty output, timeout, malformed output, secondary state failure, encryption, and IPv6. HTTP tests cover escaping, local/external titles, localized states, empty/error distinctions, and polling cadence.

Normal tests do not contact CUPS or a physical printer. Capturing active lifecycle output from the Epson reference environment remains an explicitly authorized compatibility task.
