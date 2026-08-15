# Private network deployment

Easy Print has no application login in v1.0. Any network peer that can reach the web port can use the available print workflows and view the metadata exposed by the interface. Choose one private access pattern deliberately, restrict it at the network boundary, and keep the CUPS administration port separate.

Public internet exposure, Tailscale Funnel, router port forwarding, and an unrestricted public tunnel are unsupported.

## Choose an access pattern

| Pattern | Browser transport | Recommended use | Application bind |
| --- | --- | --- | --- |
| Tailscale Serve | HTTPS inside the tailnet | Remote/private access with managed TLS | `127.0.0.1:8080` |
| HTTPS reverse proxy | HTTPS on a controlled LAN or tailnet | Existing Nginx/TLS infrastructure and edge limits | `127.0.0.1:8080` |
| Direct LAN | HTTP on a trusted segment | Small isolated networks without a TLS endpoint | Exact LAN host address |

Tailscale Serve is the simplest remote-access pattern. An HTTPS reverse proxy gives the operator explicit header, body, timeout, certificate, and HSTS control. Direct LAN access is acceptable only when every device on that segment is trusted to use Easy Print.

## Common baseline

Start with the loopback-only defaults:

```dotenv
WEB_BIND_ADDRESS=127.0.0.1
WEB_PORT=8080
CUPS_BIND_ADDRESS=127.0.0.1
CUPS_PUBLIC_PORT=631
```

The web container reaches `cups:631` over the private Compose network. Publishing host port 631 is not required for queue discovery, submission, status, or cancellation. Keep the host firewall deny-by-default and open only the chosen Easy Print entry point to its intended sources.

After changing `.env`, validate and restart the stack:

```bash
docker compose config --quiet
docker compose up --detach --build --wait
```

## Tailscale Serve over HTTPS

Keep Easy Print on loopback and enable secure cookies:

```dotenv
WEB_BIND_ADDRESS=127.0.0.1
WEB_PORT=8080
COOKIE_SECURE=true
CUPS_BIND_ADDRESS=127.0.0.1
```

On the Docker host, publish the local service persistently inside the tailnet:

```bash
tailscale serve --bg 8080
tailscale serve status
```

Current Tailscale Serve accepts a local port as its reverse-proxy target, provisions HTTPS for the node's tailnet DNS name, and keeps Serve traffic inside the tailnet. See the official [Serve CLI reference](https://tailscale.com/docs/reference/tailscale-cli/serve) before automating the command because its syntax changed in earlier client releases.

Restrict the host and HTTPS port with a reviewed tailnet policy. Tailscale recommends grants for new policies. This illustrative rule assumes the operator has already defined `group:print-users` and tagged the host as `tag:print-server`:

```json
{
  "grants": [
    {
      "src": ["group:print-users"],
      "dst": ["tag:print-server"],
      "ip": ["tcp:443"]
    }
  ]
}
```

Validate selectors against the current [Tailscale grants syntax](https://tailscale.com/docs/reference/syntax/grants) and test the policy from both an allowed and a denied device. Easy Print deliberately ignores Tailscale identity headers because it has no identity or authorization model; do not treat those headers as an application login.

Tailscale Serve does not replace the PHP and application request limits. If the deployment requires configurable rejection at an HTTP edge before PHP, proxy through Nginx locally and point Serve at that loopback proxy instead. Never substitute `tailscale funnel`; Funnel is public.

To remove the Serve configuration:

```bash
tailscale serve reset
```

## HTTPS reverse proxy

Keep the Compose web port on loopback and set `COOKIE_SECURE=true`. Terminate TLS in a maintained proxy on the same host or another explicitly trusted boundary.

The [Nginx server fragment](../deploy/nginx/easy-print-server.conf.example) provides:

- the same 26 MiB body and 16 KiB aggregate header capacity as the shipped application defaults;
- request buffering before PHP;
- bounded client-header, client-body, upstream connection, read, and send timeouts;
- an upstream restricted to `127.0.0.1:8080`; and
- overwritten forwarding headers rather than a client-supplied forwarding chain.

Include the fragment inside the TLS `server` block and supply the deployment's reviewed certificate, key, server name, and TLS policy separately. If the proxy is not on the Docker host, bind Easy Print only to the proxy-facing private interface and firewall it to that proxy.

Easy Print does not currently derive authorization, client identity, redirects, or cookie policy from forwarded headers. `COOKIE_SECURE` is explicit configuration. The proxy should still overwrite `Host`, `X-Forwarded-For`, `X-Forwarded-Host`, and `X-Forwarded-Proto` so future request logs receive an unambiguous boundary.

Add HSTS at the proxy only after the chosen hostname is permanently HTTPS-only. Do not emit HSTS on a direct-IP or mixed HTTP/HTTPS endpoint. When `APP_BASE_PATH` is non-empty, mount the proxy at the same path and preserve it upstream; the shipped fragment assumes `/`.

## Direct trusted-LAN access

Bind to one address assigned to the Docker host, not to every interface. The reserved TEST-NET address below is documentation only and must be replaced:

```dotenv
WEB_BIND_ADDRESS=192.0.2.10
WEB_PORT=8080
COOKIE_SECURE=false
CUPS_BIND_ADDRESS=127.0.0.1
```

Allow TCP port 8080 only from the intended LAN segment in the host firewall. Do not open the port on a guest, IoT, or untrusted Wi-Fi network. Direct HTTP cannot use `Secure` cookies; if the network is not fully trusted, use Tailscale Serve or an HTTPS reverse proxy instead.

Binding `0.0.0.0` is intentionally absent from the example. It can expose Easy Print on LAN, virtual, container, and public interfaces simultaneously and should not be used as a shortcut.

## CUPS administration access

Easy Print consumes configured queues and does not administer CUPS. Routine users need only the Easy Print port. Keep `CUPS_BIND_ADDRESS=127.0.0.1` even when the application is available on the LAN or tailnet.

When an operator must use the CUPS web interface, prefer a temporary authenticated tunnel rather than a permanent port publication. For example, use an SSH connection to the print host with a local forward from an operator workstation, then browse the forwarded loopback port:

```bash
ssh -L 8631:127.0.0.1:631 operator@print-host
```

Open `http://127.0.0.1:8631` only while the tunnel is active. CUPS authorization, TLS, and administrative policy remain owned by the CUPS service; Easy Print does not weaken or replace them.

## Verification checklist

From an allowed browser/network path:

1. Open Easy Print using the intended hostname and confirm no direct CUPS URL is needed.
2. Confirm HTTPS deployments set both `easy_print_session` and `easy_print_queue` cookies with `Secure` after queue selection.
3. Inspect the HTML response for CSP, `X-Content-Type-Options`, framing, referrer, and cache headers.
4. Send an empty `POST` to a non-mutating test path and confirm a generic `403`, demonstrating the global CSRF boundary.
5. Verify the proxy rejects a request above its configured body limit and Easy Print returns `413` at its own boundary.
6. Confirm a denied LAN segment or tailnet identity cannot establish a TCP connection to the web entry point.
7. Confirm host port 631 remains unreachable from routine user devices.
8. Run `docker compose exec --no-TTY web php /app/bin/check-cups.php` to verify the private web-to-CUPS path without printing.

Record the chosen pattern, hostname, firewall or grant owner, certificate renewal mechanism, and date of the last access test in the operator's private runbook. Do not commit real addresses, tailnet names, certificates, or credentials to this repository.
