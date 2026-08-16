# CUPS queue discovery

Easy Print discovers queues through read-only `lpstat` calls. It does not add, remove, configure, pause, or resume printers.

## Command sequence

For the configured CUPS server, the adapter executes these argument arrays through the bounded process runner:

```text
lpstat -h <host>:<port> -r
lpstat -h <host>:<port> -d
lpstat -h <host>:<port> -e
```

`-E` is prepended when `CUPS_ENCRYPTION=required`. IPv6 literals are bracketed before the port is appended. The host and port come only from validated runtime configuration; browser input never reaches these arguments.

The three calls deliberately avoid device URIs, job names, and other fields that are unnecessary for discovery and may contain private identifiers.

## Result contract

A snapshot always reports one connectivity state:

| State | Meaning |
| --- | --- |
| `available` | The scheduler responded with recognized output; the queue list may still be empty |
| `unavailable` | The scheduler is stopped, unreachable, or the client cannot start |
| `unauthorized` | CUPS rejected the read operation as unauthorized or forbidden |
| `timed_out` | The bounded process deadline expired |
| `malformed_response` | Successful output was unrecognized, duplicated, invalid, or exceeded the output bound |

Only an available snapshot can contain queue identifiers or a default identifier. Queue identifiers are treated as opaque strings, retain their output order, and must be escaped at the later HTML rendering boundary.

Raw stdout and stderr do not cross into the domain result. Operational logging will use sanitized error codes in the observability phase.

## Smoke check

After dependencies are installed and a CUPS server is reachable, run:

```bash
php bin/check-cups.php
```

The command prints a small JSON snapshot and exits with status `0` only when connectivity is `available`. It is read-only and never submits or cancels a job.

With the Docker reference topology:

```bash
docker compose up -d cups
docker compose run --rm web php /app/bin/check-cups.php
```

Use environment overrides for an external CUPS server only on a trusted network. Never commit private addresses or raw command output.
