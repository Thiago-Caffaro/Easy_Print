# Docker Compose reference topology

The reference package targets an AMD64 Linux Docker host. It keeps the web application and CUPS/Avahi in separate containers because their privileges, dependencies, and lifecycles differ.

## Start the stack

```bash
cp .env.example .env
docker compose config
docker compose up --build
```

Default host bindings are deliberately local:

- Easy Print: `127.0.0.1:8080`;
- CUPS: `127.0.0.1:631`.

Set `WEB_BIND_ADDRESS` or `CUPS_BIND_ADDRESS` in `.env` only when access is protected by the host firewall, a trusted LAN, Tailscale, or a reverse proxy. Public internet exposure is unsupported.

## Service boundaries

### Web

- Contains PHP, the application, and CUPS client utilities.
- Has a persistent application-data volume and a private tmpfs for uploaded documents.
- Runs as UID/GID 10001 with a read-only root filesystem, no Linux capabilities, and no USB devices.
- Applies SQLite migrations before starting the HTTP process.

### CUPS and Avahi

- Owns queue configuration, spool data, drivers or printer applications, mDNS, and device access.
- Persists `/etc/cups` and `/var/spool/cups` in separate volumes.
- Allows normal print access from the local container network while restricting administration to the container-local interface by default.
- Receives any required USB device mapping or host-network configuration from the operator; the web service never receives it.

## Avahi and USB

mDNS behavior depends on the Linux host and network mode. The bridge-mode Compose file starts cleanly and preserves service separation, but discovering printers outside the Docker network may require an operator-specific host-network or mDNS reflector configuration for the CUPS service.

USB device paths are intentionally absent from the shared Compose file because they are host-specific. Add the minimum device mapping to an uncommitted override file after resolving the stable device path and permissions.

TrueNAS SCALE and ARM64 are not validated by this Foundation package. TrueNAS guidance will map the same boundaries to datasets, networking, and USB permissions; ARM64 remains outside v1.0.
