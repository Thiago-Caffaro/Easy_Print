<div align="center">
  <img src="docs/assets/easy-print-logo.svg" alt="Easy Print logo" width="220">
  <h1>Easy Print</h1>
  <p><strong>A focused, printer-agnostic web interface for CUPS on self-hosted Linux.</strong></p>
  <p>Upload a document, choose a discovered queue, use only its real capabilities, and follow the print job without exposing users to the CUPS administration UI.</p>

  <p>
    <img alt="Project status: planning" src="https://img.shields.io/badge/status-planning-64748B?style=for-the-badge">
    <img alt="PHP 8.5" src="https://img.shields.io/badge/PHP-8.5-777BB4?style=for-the-badge&logo=php&logoColor=white">
    <img alt="Slim 4" src="https://img.shields.io/badge/Slim-4-74BF43?style=for-the-badge">
    <img alt="Docker Linux" src="https://img.shields.io/badge/Docker-Linux-2496ED?style=for-the-badge&logo=docker&logoColor=white">
    <a href="LICENSE"><img alt="MIT License" src="https://img.shields.io/badge/license-MIT-0F766E?style=for-the-badge"></a>
  </p>

  <p>
    <a href="#vision">Vision</a> ·
    <a href="#mvp-scope">MVP scope</a> ·
    <a href="#architecture">Architecture</a> ·
    <a href="#roadmap">Roadmap</a> ·
    <a href="https://github.com/Thiago-Caffaro/Easy_Print/wiki">Wiki</a> ·
    <a href="CONTRIBUTING.md">Contributing</a>
  </p>
</div>

> [!IMPORTANT]
> Easy Print is currently in the planning and foundation stage. It is not ready for production use yet. The repository, Project, and Wiki describe the intended v1.0 contract without presenting planned behavior as implemented.

## Vision

Easy Print is a thin web layer over an existing CUPS server. It aims to make routine printing comfortable on a trusted local network while keeping printer discovery, drivers, spooling, and device communication where they belong: in CUPS and Avahi.

The product is intentionally small:

- **Printer-agnostic by design.** Queues and capabilities are discovered at runtime.
- **CUPS-first.** Easy Print consumes queues already configured by CUPS; it does not replace or administer the print server.
- **Self-hosted.** Linux Docker is the primary platform, with TrueNAS SCALE documented as a secondary target.
- **Server-rendered.** PHP 8.5, Slim 4, HTML/CSS, and HTMX keep the runtime understandable.
- **Private by topology.** v1.0 assumes a trusted LAN or Tailscale network and has no application login.
- **Privacy-conscious.** Uploaded documents are removed after CUPS accepts them; only operational metadata and sanitized errors remain.

## MVP scope

| Capability | v1.0 intent |
| --- | --- |
| CUPS connectivity | Validate one configured CUPS server and report availability |
| Printer queues | List and select queues already configured in CUPS |
| Dynamic options | Render only capabilities advertised by the selected queue |
| Uploads | Accept and validate PDF, PNG, and JPEG |
| Printing | Submit safe `lp` arguments and capture the CUPS job identifier |
| Queue management | Show active jobs, state, and supported cancellation |
| History | Store metadata and sanitized errors in SQLite; never retain documents |
| Interface | Portuguese first, translation-ready for English |
| Updates | Server-rendered pages with HTMX polling and minimal JavaScript |
| Deployment | AMD64 Linux Docker Compose; TrueNAS SCALE guide follows |

### Explicit non-goals for v1.0

- Adding, removing, or configuring printers in CUPS.
- Direct USB or mDNS access from the web application.
- Public internet exposure, SaaS tenancy, billing, or user accounts.
- Office-document conversion, scanner support, or ARM64 images.
- Hardcoded Epson-only settings or promises about unsupported hardware features.

## Development snapshot

The current development branches provide the first executable Slim application, validated environment configuration, Portuguese and English catalogs, a transactional SQLite migration mechanism, a bounded allowlisted process runner, the separate web/CUPS Docker Compose topology, and read-only CUPS queue discovery.

```bash
cp .env.example .env
docker compose up --build
```

The web interface binds to `127.0.0.1:8080` and CUPS binds to `127.0.0.1:631` by default. Change bind addresses only when the host firewall, LAN, Tailscale, or reverse proxy is intentionally providing the access boundary.

For local PHP development and all quality commands, see [Development](docs/development.md). Runtime variables are documented in [Configuration](docs/configuration.md), migrations and retention fields are covered in [Database](docs/database.md), and runtime contracts are described in [CUPS queue discovery](docs/cups-discovery.md), [Queue selection and state](docs/queue-selection.md), [Secure PDF uploads](docs/pdf-uploads.md), [Secure image uploads](docs/image-uploads.md), [HTTP security](docs/http-security.md), [Private network deployment](docs/network-deployment.md), and [Web container security](docs/container-security.md).

## Architecture

```mermaid
flowchart LR
    U["Browser<br/>Portuguese UI"] -->|HTTP / HTMX| W["Easy Print web<br/>PHP 8.5 + Slim 4"]
    W -->|Validated client commands| C["CUPS server"]
    A["Avahi / mDNS"] -->|Device discovery| C
    C --> Q["Configured printer queues"]
    Q --> P1["USB printers"]
    Q --> P2["IPP / network printers"]
    W -->|Metadata and sanitized errors| S[("SQLite")]
    W -->|Temporary document| T["Private temporary storage"]
    T -. deleted after spool acceptance .-> X["Cleanup"]
```

The reference package uses separate containers for the web application and CUPS/Avahi. The web container receives only the network and storage access it needs; it does not receive the USB device or the complete CUPS spool.

### Proposed code boundaries

```text
app/
├── Application/       Use cases and DTOs
├── Domain/            Queue, capability, print job, and history rules
├── Infrastructure/    CUPS, SQLite, process, filesystem, and clock adapters
├── Http/              Slim actions and middleware
├── Translation/       pt-BR and en catalogs
└── Views/             Pages, HTMX fragments, and view models
config/                Validated runtime configuration
public/                Front controller and public assets only
storage/               Private runtime state and temporary files
tests/
├── Unit/
├── Integration/
└── Fixtures/Cups/     Sanitized deterministic CUPS outputs
```

Directories will be introduced with the first vertical slices instead of being committed empty.

## Core business flow

1. Check the configured CUPS server and enumerate its queues.
2. Select a queue and read its defaults, state, and supported values.
3. Validate the uploaded PDF, PNG, or JPEG outside the public webroot.
4. Validate every print option against the selected queue capability snapshot.
5. execute an allowlisted CUPS client command with separated arguments and a timeout.
6. Capture the CUPS job identifier and delete the uploaded document after spool acceptance.
7. Reconcile job state from CUPS while recording metadata and sanitized failures locally.

## Security model

Easy Print treats the browser, uploaded files, queue names, CUPS output, and print-option values as untrusted data. The implementation must provide CSRF protection, format-specific upload validation, random private filenames, path containment, process timeouts, command allowlists, option allowlists, safe error rendering, and bounded logs.

Network trust is a deployment requirement, not a substitute for application safety. See the [Security Policy](SECURITY.md) and the [Wiki security model](https://github.com/Thiago-Caffaro/Easy_Print/wiki/Security-Model).

## Deployment model

The supported reference topology will be delivered through Docker Compose:

```text
compose.yaml
├── web          Easy Print, PHP runtime, CUPS client tools
└── cups         CUPS, Avahi, printer drivers/applications, USB/network access
```

Linux Docker on AMD64 is the primary target. TrueNAS SCALE uses the same service boundaries with platform-specific storage, networking, and USB guidance.

## Roadmap

| Phase | Outcome |
| --- | --- |
| Foundation | Repository governance, PHP/Slim bootstrap, container topology, CI, configuration, fixtures |
| MVP Printing | Queue discovery, capability-driven form, PDF/PNG/JPEG upload, submission, jobs, cancellation, history |
| Operational Hardening | Security controls, cleanup, observability, production containers, recovery documentation |
| v1.0 Readiness | English translation, accessibility, real-printer matrix, release packaging, screenshots |
| Future Exploration | Direct IPP evaluation, office formats, scanning, ARM64 |

The actionable backlog lives in [GitHub Issues](https://github.com/Thiago-Caffaro/Easy_Print/issues) and the linked [GitHub Project](https://github.com/Thiago-Caffaro/Easy_Print/projects).

## Documentation

- [Project Wiki](https://github.com/Thiago-Caffaro/Easy_Print/wiki) — architecture, domain model, operations, testing, and troubleshooting.
- [Contributing Guide](CONTRIBUTING.md) — development and pull-request workflow.
- [Security Policy](SECURITY.md) — supported versions and private reporting.
- [Architecture decisions](docs/decisions/) — decisions that must evolve with code.

## Contributing

The project welcomes focused Issues and pull requests. Please read [CONTRIBUTING.md](CONTRIBUTING.md), start from an accepted Issue, keep changes small, and include verification evidence.

## License

Easy Print is available under the [MIT License](LICENSE).

<div align="center">
  <sub>Designed for calm, predictable printing on networks you control.</sub>
</div>
