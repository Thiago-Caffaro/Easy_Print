# CUPS queue capabilities

Easy Print treats the selected CUPS queue as the authority for print-time controls. It never assumes that a printer supports color, duplex, a tray, a media type, a resolution, or any other feature because of its make or model.

## Read-only discovery

The adapter executes one bounded command through the allowlisted process runner:

```text
lpoptions -h <host>:<port> -p <selected-queue> -l
```

`-E` is prepended when CUPS encryption is required. The queue value must have come from the latest queue discovery snapshot; it remains a separated process argument and is validated against the CUPS queue-name boundary before execution.

OpenPrinting documents `lpoptions -l` as the CUPS command for listing printer-specific options and their current settings. Available options vary by destination. See the official [lpoptions manual](https://openprinting.github.io/cups/doc/man-lpoptions.html) and [printing options guide](https://openprinting.github.io/cups/doc/options.html).

## Normalized contract

Each option keeps four distinct concepts:

- the technical option identifier passed to CUPS later;
- the driver-provided diagnostic label;
- a stable Easy Print category used to select a translated UI label; and
- bounded technical choice identifiers with an explicit nullable default.

The parser recognizes safe aliases in these categories:

| Category | Typical advertised identifiers |
| --- | --- |
| Media size | `PageSize`, `media` |
| Media type | `MediaType`, `media-type` |
| Color mode | `ColorModel`, `ColorMode`, `print-color-mode` |
| Quality | `PrintoutMode`, `OutputMode`, `print-quality`, `cupsPrintQuality` |
| Resolution | `Resolution`, `printer-resolution` |
| Orientation | `Orientation`, `orientation-requested` |
| Sides | `Duplex`, `sides` |
| Media source | `InputSlot`, `media-source` |
| Scaling | `scaling`, `print-scaling`, `PageScaling`, `fit-to-page` |

Matching is case-insensitive while the original technical identifier is preserved. A category appears only when CUPS advertises it. For example, the application must not show duplex when no sides/duplex option exists.

Unknown driver options remain in the normalized snapshot with category `unknown`, their bounded identifier, label, choices, and default. This makes fixtures and operator diagnostics useful without automatically turning arbitrary driver data into an HTTP form control. Only known categories are renderable. Issue #15 will separately validate submitted values against the active snapshot before producing any `lp` arguments.

Empty successful output means the queue advertised no printer-specific options. Invalid lines or encoding, duplicate options/choices, multiple defaults, unsafe identifiers, oversized collections, bounded-output failures, and null bytes produce `malformed_response`; raw output never crosses the adapter boundary or enters application logs.

## Fingerprint and cache

The normalized ordered option set is encoded deterministically and hashed with SHA-256. The fingerprint includes technical identifiers, driver labels, categories, choices, and defaults.

Available snapshots use the SQLite `capability_snapshots` cache. Entries are:

- partitioned by `CUPS_SERVER_KEY` and exact queue identifier, so changing queues cannot reuse another queue's controls;
- validated through the same bounded domain constructors when decoded;
- ignored and removed when expired or corrupted;
- replaced atomically when a refresh produces a different driver/capability fingerprint; and
- disabled entirely when `CAPABILITY_CACHE_TTL_SECONDS=0`.

The default TTL is 60 seconds. CUPS command-line tools do not expose a portable queue configuration revision in this flow, so a same-name queue whose driver changes can remain stale only until that bounded TTL expires. Queue removal is handled by current queue discovery: an unlisted queue cannot be selected, and its unused cache entry expires. Direct IPP revision attributes are intentionally deferred to the evidence-based adapter evaluation in issue #35.

## Smoke check

After migrations have run and the target queue appears in queue discovery, inspect its normalized contract without submitting a job:

```bash
php bin/check-capabilities.php REFERENCE_QUEUE
```

The command first verifies the exact queue against the latest CUPS snapshot, then prints technical capability metadata as JSON. It never sends a document or changes a queue. Do not paste output containing real queue identifiers into a public issue; sanitize and capture it through the fixture workflow instead.

## Evidence boundary

Synthetic contracts cover media size/type, color, quality, resolution, orientation, sides, source, scaling, unknown options, and missing categories. They validate parser behavior only. Real Epson L4150 and additional driver output remains part of the sanitized capture work in issue #3; no compatibility claim should be made before that evidence is committed and reviewed.
