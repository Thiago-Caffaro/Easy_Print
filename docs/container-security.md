# Web container security

The reference web image is an AMD64 Linux container for the Easy Print application and CUPS client tools. It does not contain a CUPS daemon, printer drivers, USB device access, the CUPS spool, or build dependencies.

## Runtime boundary

The final image:

- uses the pinned PHP 8.5 patch release declared in `docker/web/Dockerfile`;
- contains only runtime libraries, required PHP extensions, application source, production Composer dependencies, and CUPS clients;
- runs as the fixed `easyprint` user with UID/GID 10001;
- writes only to the `/var/lib/easy-print` volume and declared tmpfs mounts;
- runs with a read-only root filesystem, all Linux capabilities dropped, and `no-new-privileges` in Compose;
- exposes only the application port and reaches CUPS through the private backend network;
- publishes OCI source, revision, version, creation, license, and documentation labels.

At startup, PHP receives the configured upload and request-body limits and accepts only one uploaded file per request. Browser-facing limits and headers are also enforced inside the Slim application. The private CSRF signing secret is generated with operating-system randomness in the temporary tmpfs and receives mode `0600`; restarting the container intentionally invalidates existing anonymous browser sessions.

The Compose health check exercises the local HTTP endpoint. It does not print, mutate a queue, or grant access to the CUPS administration interface.

## Build inputs and metadata

Composer dependencies are resolved from `composer.lock` in a separate build stage. The Composer binary, PHP base image, Node.js tooling, GitHub Actions, and Trivy version are explicit. Dependabot tracks Docker, Composer, and Actions updates.

The build accepts these metadata-only arguments:

| Argument | Local default | Purpose |
| --- | --- | --- |
| `EASY_PRINT_VERSION` | `dev` | Version recorded in the OCI image label |
| `EASY_PRINT_REVISION` | `unknown` | Source commit recorded in the OCI image label |
| `EASY_PRINT_CREATED` | Unix epoch | RFC 3339 build timestamp recorded in the OCI image label |

CI uses the commit SHA for the revision and a stable timestamp so repeated validation builds remain comparable. A release workflow may supply the real release timestamp and semantic version.

Base-image tags deliberately include exact PHP and Composer patch versions while Dependabot supplies reviewed updates. Rebuilding may still incorporate patched Alpine packages. Record the resulting image digest for a release; do not assume that rebuilding a tag later produces the same digest.

## CI verification

The container job:

1. builds both reference images from a clean checkout;
2. checks the final image user, required extensions, OCI source label, and absence of Composer/GCC;
3. executes the image with a read-only root filesystem;
4. installs Trivy 0.72.0 from the official release only after checking its pinned SHA-256;
5. rejects fixable high or critical operating-system/library vulnerabilities;
6. starts the real Compose boundary and verifies the web container remains non-root and read-only;
7. checks security headers, mutation rejection, and the effective body limit over the real HTTP port; and
8. runs the read-only CUPS connectivity smoke check.

`--ignore-unfixed` prevents an upstream vulnerability with no available remediation from making every pull request permanently red. Such findings remain an operator/release-review concern and must not be silently added to an ignore file. A vulnerability exception requires a documented risk decision with an expiry and a linked Issue.

## Local inspection

```bash
docker compose build --pull web cups
docker image inspect easy-print-web:local
docker compose up --detach --wait cups web
docker compose exec --no-TTY web php /app/bin/check-cups.php
docker compose down --volumes --remove-orphans
```

The production host should restrict the published address to localhost, the intended LAN interface, or a Tailscale/reverse-proxy boundary. Public internet exposure is outside the v1.0 threat model.

When a reverse proxy terminates HTTPS, set `COOKIE_SECURE=true`, apply body and header limits no higher than the Easy Print values, and configure HSTS at that TLS boundary. Easy Print does not emit HSTS itself because the reference service also supports direct localhost HTTP. See [HTTP security](http-security.md).
