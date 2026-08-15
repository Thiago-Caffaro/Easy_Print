# Bounded process runner

All CUPS client utilities execute through `AllowedProcessRunner`. The boundary accepts a logical executable key and a list of argument strings; it never accepts a shell command line.

## Guarantees

- Only keys from the operator-provided executable map can run.
- Executable and arguments remain separate all the way to the operating-system process API.
- Null bytes and unapproved environment overrides are rejected.
- The working directory, locale, path, timeout, and combined output limit are explicit.
- Parent environment names are removed unless the application deliberately reintroduces them.
- Timeout, output truncation, start failure, invalid arguments, disallowed programs, and non-zero exits become structured results.

The runner uses Symfony Process because its cross-platform process supervision reliably enforces timeouts on Windows and Linux. This dependency is contained inside the infrastructure boundary; application and domain code do not import it.

The runner does not make CUPS options safe by itself. Queue names, option keys/values, copies, and page ranges pass through the separate [print argument validation](print-argument-validation.md) boundary. Job identifiers and private file paths still require their dedicated adapters before they become arguments.
