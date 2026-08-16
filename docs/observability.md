# Observability and health

Easy Print emits a small, bounded operational signal set to container standard error. It does not require a monitoring vendor, log database, or metrics service. Operators can collect the stream with Docker or their existing host tooling.

## Correlation IDs

Every HTTP request receives a server-generated 32-character lowercase hexadecimal identifier. The same value is:

- attached to the request as `easy_print.correlation_id` for actions and future use cases;
- available automatically to the structured logger and injected adapters; and
- returned to the browser in `X-Request-ID`, including generic error and rejection responses.

Client-supplied `X-Request-ID` values are ignored. This prevents an attacker from injecting unbounded or misleading identifiers into logs. A correlation scope is cleared after its response so a long-running PHP process cannot leak one request's identifier into the next.

When reporting a failure internally, record the response identifier, approximate time, and user-visible operation. Never attach the uploaded document or raw container logs to a public Issue.

## Structured application logs

Each application record is one JSON object on standard error:

```json
{"timestamp":"2026-01-01T12:00:00Z","level":"info","event":"http.request.completed","correlation_id":"0123456789abcdef0123456789abcdef","context":{"method":"GET","status":200,"duration_ms":4}}
```

The schema is intentionally small:

| Field | Contract |
| --- | --- |
| `timestamp` | UTC, second precision |
| `level` | PSR-3 level at or above `LOG_LEVEL` |
| `event` | Bounded stable event name, never an exception message |
| `correlation_id` | Present while handling an HTTP request |
| `context` | At most 12 scalar entries with bounded keys and values |

Control characters are replaced, event and key lengths are bounded, and each allowed operational field has its own value type, range, or finite value set. Every unknown key or invalid value is replaced with `[redacted]`. The allowlist excludes authorization, cookies, tokens, credentials, document names/content, paths, commands, CUPS queue identifiers/hosts, and raw stdout/stderr. Exceptions contribute only a validated class name, never message, trace, arguments, or file path.

Current stable events include:

- `http.request.completed` with method, status, and duration;
- `http.request.rejected` for framework HTTP exceptions;
- `http.request.failed` for unexpected action/middleware failures;
- `http.middleware.failed` for failures outside the framework error boundary;
- `cups.queue_discovery.completed` with connectivity and queue count; and
- `health.<component>.unavailable` for readiness failures.

Dependency adapters receive the same logger, so records produced during a request inherit its correlation ID without placing HTTP concepts in their interfaces. The dependency-free liveness request is logged at `debug` and therefore does not create an application record at the default `info` level.

PHP itself and the built-in HTTP runtime may emit their own diagnostic lines. Easy Print application events remain JSON lines; collectors should parse JSON records and retain non-JSON runtime diagnostics separately. Browser responses never contain those diagnostics.

## Health endpoints

Both endpoints are read-only `GET` routes under `APP_BASE_PATH`. They never create storage, submit, cancel, or administer a CUPS job and never return queue identifiers, paths, database details, or exception messages.

| Endpoint | Purpose | Dependencies | HTTP behavior |
| --- | --- | --- | --- |
| `/health/live` | Prove the PHP/Slim process can answer | None | `200` with application `ok` |
| `/health/ready` | Report operational dependencies | Storage, migrated SQLite, CUPS discovery | `200` for `ok`/`degraded`; `503` for critical local failure |

`/health/ready` returns deterministic JSON:

```json
{
  "status": "degraded",
  "checks": {
    "application": "ok",
    "storage": "ok",
    "database": "ok",
    "cups": "timed_out"
  }
}
```

Storage and database are critical local state. A missing/unwritable private directory, unavailable SQLite file, or absent migration table produces `status=unavailable` and HTTP `503`.

CUPS is an external dependency and its existing structured states remain visible: `available`, `unavailable`, `unauthorized`, `timed_out`, or `malformed_response`. A CUPS failure produces `status=degraded` with HTTP `200`; restarting a healthy web process cannot repair an external print server, and non-printing application surfaces may remain useful. Alert on the JSON state rather than interpreting HTTP 200 alone.

The container `HEALTHCHECK` calls only `/health/live`. Dependency monitoring may poll `/health/ready`, but should not do so more frequently than the operational need because it performs a real read-only CUPS discovery.

## Operator triage

```bash
curl --fail http://127.0.0.1:8080/health/live
curl --silent --show-error http://127.0.0.1:8080/health/ready
docker compose logs --since 15m web
docker compose exec --no-TTY web php /app/bin/check-cups.php
```

Use this order:

1. If liveness fails, inspect container state, startup/migration output, memory, and the published bind.
2. If readiness is `unavailable`, inspect volume ownership/free space and migration startup before restarting.
3. If readiness is `degraded`, use the CUPS check and inspect only the CUPS container/network/printer path.
4. Filter JSON application records by `correlation_id` and compare stable events/statuses.
5. Keep raw documents, request bodies, cookies, CUPS output, and secrets out of tickets and long-term logs.

Retention, shipping, alerting, and dashboards are deployment choices. External monitoring vendor integration is outside v1.0.
