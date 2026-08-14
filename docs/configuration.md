# Runtime configuration

Configuration is loaded once during application composition and converted into typed values. Invalid values fail startup with the setting name and an actionable constraint; the invalid value itself is not repeated in the error.

Committed defaults contain no credentials or private addresses. `.env.example` is the authoritative inventory.

| Variable | Default | Validation and purpose |
| --- | --- | --- |
| `APP_ENV` | `production` | `production`, `development`, or `testing` |
| `APP_DEBUG` | `false` | Boolean development signal; browser error details remain disabled |
| `APP_BASE_PATH` | empty | Optional absolute URL path without traversal segments |
| `APP_LOCALE` | `pt-BR` | Default interface locale; must be enabled |
| `APP_ENABLED_LOCALES` | `pt-BR,en` | Comma-separated subset of the shipped catalogs |
| `CUPS_HOST` | `cups` | DNS hostname, IPv4 address, or IPv6 address without a scheme or port |
| `CUPS_PORT` | `631` | Integer from 1 through 65535 |
| `CUPS_ENCRYPTION` | `never` | `never` or `required`; adapter behavior is introduced with CUPS connectivity |
| `CUPS_SERVER_KEY` | `primary` | Non-secret stable identifier stored with job metadata |
| `CUPS_LP_PATH` | `/usr/bin/lp` | Absolute allowlisted executable path |
| `CUPS_LPSTAT_PATH` | `/usr/bin/lpstat` | Absolute allowlisted executable path |
| `CUPS_LPOPTIONS_PATH` | `/usr/bin/lpoptions` | Absolute allowlisted executable path |
| `CUPS_CANCEL_PATH` | `/usr/bin/cancel` | Absolute allowlisted executable path |
| `DATABASE_PATH` | project storage path | Absolute SQLite file path |
| `TEMPORARY_PATH` | project storage path | Absolute private temporary directory |
| `UPLOAD_MAX_BYTES` | `26214400` | 1 KiB through 100 MiB; HTTP streaming enforcement follows in the upload slice |
| `TEMP_FILE_TTL_SECONDS` | `3600` | 60 seconds through 24 hours |
| `HISTORY_RETENTION_DAYS` | `90` | 1 through 3650 days |
| `ERROR_RETENTION_DAYS` | `30` | 1 through 365 days |
| `CAPABILITY_CACHE_TTL_SECONDS` | `60` | 0 through 3600 seconds |
| `PROCESS_TIMEOUT_SECONDS` | `15` | 1 through 120 seconds |
| `PROCESS_OUTPUT_MAX_BYTES` | `262144` | 1 KiB through 1 MiB across stdout and stderr |

The process executable variables are deployment configuration, not browser input. Future CUPS adapters refer to the logical allowlist keys and never accept an executable or option name from an HTTP request.
