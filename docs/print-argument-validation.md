# Print argument validation

Easy Print converts untrusted form fields into a deterministic argument fragment before any process adapter can call `lp`. This application boundary does not execute a process and does not accept a document path.

## Required current state

Validation receives the latest queue snapshot, the active capability snapshot, and the submitted form values. It rejects the request unless all of these remain true:

1. CUPS queue discovery is available.
2. The exact submitted queue still exists in that snapshot.
3. Capability discovery is available for that same queue.
4. The submitted SHA-256 capability fingerprint exactly matches the active snapshot.

A removed queue produces `queue_changed`; a queue/fingerprint mismatch produces `stale_capabilities`. Both are explicit safe signals for the HTTP layer to refresh and re-render the form instead of guessing, silently changing settings, or submitting with stale values.

## Copies and page ranges

Copies use the native `lp -n` flag and must match `[1-9][0-9]{0,2}` with a numeric value from 1 through 999. Leading signs, zero, leading zeros, whitespace, decimals, exponent notation, and longer values are rejected.

An optional page range uses the native `lp -P` flag. It is at most 512 bytes and 100 comma-separated segments. Each segment is either a page from 1 through 999999 or an ascending inclusive range such as `3-8`. Empty segments, whitespace, leading zeros, descending ranges, punctuation, and shell-like text are rejected.

These two generic controls are included because the official OpenPrinting [`lp` manual](https://openprinting.github.io/cups/doc/man-lp.html) defines their exact command flags. No other generic option is accepted merely because CUPS commonly supports it.

## Advertised options

Submitted option names must exactly match renderable options in the active capability snapshot. Values must exactly match one of that option's advertised technical choices. Therefore the mapper rejects:

- names absent from the snapshot;
- values absent from the corresponding choice set;
- array/object/non-string values;
- every option categorized as `unknown`, even when its name and value came from CUPS; and
- command-shaped names or values that were never advertised.

Options may be omitted to retain CUPS defaults. Accepted options are reordered according to the active snapshot rather than browser field order, making generated arguments and stored metadata deterministic.

## Output contract

A valid request produces a separated list such as:

```text
-d REFERENCE_QUEUE -n 2 -P 1,3-5 -o PageSize=A4 -o Duplex=None
```

This is an argument list, never a shell string. The submission adapter in issue #16 will add the configured CUPS server/encryption arguments and the already-validated private document path after an option terminator. It must not parse or weaken this contract.

Failures expose only stable codes:

| Code | Meaning | Refresh form |
| --- | --- | --- |
| `queue_unavailable` | Current queue discovery failed | No; show dependency state |
| `queue_changed` | Submitted queue is no longer current | Yes |
| `capabilities_unavailable` | Current capability discovery failed | No; show dependency state |
| `stale_capabilities` | Queue or fingerprint no longer matches | Yes |
| `invalid_copies` | Copy grammar/range failed | No |
| `invalid_page_range` | Page grammar/range failed | No |
| `invalid_option` | Name, type, category, or choice failed | No |

No failure includes the rejected value, driver output, command, file path, or document metadata.
