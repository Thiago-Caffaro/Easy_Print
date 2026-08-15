# HTTP security

Easy Print has no login in v1.0, but it still treats every browser request as untrusted. The HTTP boundary protects mutations, bounds input before business logic, and applies browser security policy consistently. Network access must still be restricted to the intended LAN, Tailscale network, or reverse proxy.

## CSRF contract

All `POST`, `PUT`, `PATCH`, and `DELETE` requests require both:

1. the signed `easy_print_session` anonymous-session cookie; and
2. the matching token in a form field named `_csrf` or the `X-CSRF-Token` header.

Safe `GET`, `HEAD`, and `OPTIONS` requests receive a token through the `easy_print.csrf_token` request attribute. A server-rendered form places that value in a hidden field:

```html
<input type="hidden" name="_csrf" value="escaped-token">
```

HTMX may send the same value as `X-CSRF-Token`. The token is bound with HMAC to a random anonymous browser session, and the cookie itself is signed. An injected or modified cookie cannot produce a valid token. Invalid or missing credentials receive a generic `403` response before the route action runs.

The cookie is `HttpOnly`, `SameSite=Strict`, scoped to `APP_BASE_PATH`, and gains `Secure` when `COOKIE_SECURE=true`. It contains no identity or document data. Its signing key is a private, random 32-byte file in `TEMPORARY_PATH`; a container restart rotates the key and invalidates old anonymous sessions by design.

## Request limits

| Boundary | Setting | Default | Behavior |
| --- | --- | --- | --- |
| Document validator | `UPLOAD_MAX_BYTES` | 25 MiB | Checks declared and actual uploaded-file size |
| PHP upload parser | `UPLOAD_MAX_BYTES` | 25 MiB | Sets `upload_max_filesize` in the container entrypoint |
| PHP request parser | `REQUEST_BODY_MAX_BYTES` | 26 MiB | Sets `post_max_size`, leaving multipart overhead |
| Application body | `REQUEST_BODY_MAX_BYTES` | 26 MiB | Rejects oversized declared or known stream sizes with `413` |
| Application headers | `REQUEST_HEADER_MAX_BYTES` | 16 KiB | Rejects an oversized parsed header block with `431` |
| PHP upload count | fixed | 1 | Sets `max_file_uploads=1` in the container entrypoint |

The application rejects malformed `Content-Length` values with `400`. Format-specific upload inspection remains authoritative for actual stored-file size and content because transport declarations are untrusted.

A reverse proxy is optional and is not included in the reference Compose topology. When one is used, it must reject request bodies and headers at the same or stricter limits before proxying. The versioned [Nginx server fragment](../deploy/nginx/easy-print-server.conf.example) applies the defaults above and enables request buffering so rejection occurs before the body reaches PHP. Replace its upstream name for the local topology and include it inside the HTTPS `server` block. If custom environment limits are lower, lower the proxy values at the same time.

PHP does not expose a portable per-application request-header directive, so the application check is defense in depth rather than a replacement for a proxy transport limit. Nginx's header buffers also constrain individual request lines; keep both the 4 Ã— 4 KiB aggregate capacity and the 16 KiB application ceiling.

## Browser response policy

Dynamic responses, including rejected requests and framework error pages, receive:

- a Content Security Policy limited to same-origin scripts, styles, connections, forms, and images, with objects and framing disabled;
- `X-Content-Type-Options: nosniff`;
- `Referrer-Policy: no-referrer`;
- `X-Frame-Options: DENY` as legacy framing defense alongside CSP;
- same-origin opener and resource policies;
- a restrictive permissions policy for camera, geolocation, microphone, payment, and USB APIs;
- `X-XSS-Protection: 0`, disabling obsolete browser filtering; and
- `Cache-Control: no-store`, preventing pages containing operational state or CSRF tokens from being retained.

HSTS belongs at the HTTPS reverse proxy. Emitting it from the application would be unsafe for the supported direct-HTTP localhost path and would not prove that the original browser connection used TLS.

## Extension rules

- Never implement state changes on a safe HTTP method.
- Never disable CSRF middleware for a mutation route.
- Escape the token with the normal HTML view helper before placing it in a form.
- Keep inline scripts and styles out of templates; the CSP intentionally omits `unsafe-inline`.
- Serve HTMX and other browser assets from the same origin instead of a CDN.
- Keep browser rejection text generic; operational details belong in sanitized server logs.
