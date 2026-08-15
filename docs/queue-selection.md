# Queue selection and state

The home page renders the latest queue snapshot and allows a browser to select one current queue. Selection is local to that browser and requires no account or server-side preference record.

## Resolution order

For every request, the application resolves selection in this order:

1. a `queue` query value that exactly matches an identifier in the latest snapshot;
2. the persisted cookie when it still matches the snapshot;
3. the CUPS default when it is present in the snapshot;
4. the first current queue;
5. no selection when the snapshot contains no queues.

Requested and cookie values are untrusted. They are never accepted merely because they have a plausible queue-name shape. A queue removed between requests cannot remain selected: the resolver falls back to a current queue or clears the stale cookie.

## Browser persistence

The selected identifier is stored in the `easy_print_queue` cookie with these attributes:

- `HttpOnly`;
- `SameSite=Lax`;
- a one-year maximum age;
- a path restricted to the configured application base path.

The value is URL-encoded in the response header. It is not a credential and is not stored in SQLite. Operators should terminate HTTPS at a trusted reverse proxy when transport security is required; the application does not force the `Secure` attribute because plain HTTP on a trusted LAN remains a supported deployment.

Queue selection uses an idempotent navigation link. It does not submit a print job or mutate CUPS, so it is intentionally separate from the CSRF-protected print and cancellation mutations planned for later slices.

## Presentation boundary

Technical identifiers remain unchanged in domain objects and cookies. HTML templates escape every identifier and localized state label before rendering. The states presented in Portuguese and English are:

| Domain state | Meaning |
| --- | --- |
| `ready` | Queue is idle and enabled |
| `processing` | Queue reports an active print operation |
| `stopped` | Queue is disabled or stopped |
| `unavailable` | Queue existed in discovery but its current state could not be read |
| `unknown` | CUPS returned a successful but unrecognized state line |
