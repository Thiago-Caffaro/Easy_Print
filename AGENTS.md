# Easy Print repository instructions

## Product

- Build a small, self-hosted web interface for printer queues exposed by CUPS.
- Keep the domain printer-agnostic. The Epson L4150 is the first reference device, not a hardcoded product boundary.
- Treat CUPS as the source of truth for queues, printer state, capabilities, and print jobs.
- Discover and consume queues already configured in CUPS; do not administer printers in the MVP.
- Target Linux Docker first and TrueNAS SCALE second. AMD64 is the only required architecture for v1.0.
- Use PHP 8.5, Slim 4, server-rendered HTML/CSS, HTMX, and minimal JavaScript.
- Ship a Portuguese interface first and keep all user-facing strings translation-ready for English.
- Support PDF, PNG, and JPEG in the MVP. Keep scanning and office-document conversion outside the MVP.

## Working agreements

- Implement the smallest vertical slice that satisfies observable acceptance criteria.
- Prefer explicit composition over speculative abstractions.
- Keep HTTP, application, domain, CUPS integration, filesystem, and persistence concerns separated.
- Do not add a framework, service, database, or production dependency without a demonstrated need.
- Update affected tests and versioned documentation in the same change.
- Run the repository validation commands before completing work. Report missing tooling honestly.

## Security requirements

- Never concatenate user input into shell commands.
- Execute allowed programs with separated arguments, timeouts, and captured exit code/stdout/stderr.
- Validate printer queues, option names, and option values against capabilities discovered from CUPS.
- Validate uploads by size, extension, and server-side content inspection. Use random names outside the webroot.
- Delete uploaded documents after the CUPS spool accepts them. Persist metadata and sanitized errors only.
- Require CSRF protection for mutations and keep internal exception details out of browser responses.
- Assume deployment on a trusted LAN/Tailscale network without application-level login in v1.0.

## GitHub workflow

- Write documentation, Issues, pull requests, commits, code comments, and repository metadata in English.
- Use Issues for actionable work and acceptance criteria.
- Use GitHub Projects for status, priority, size, area, and phase. Do not duplicate those fields in labels.
- Use the Wiki for durable explanatory architecture and operations documentation.
- Keep version-coupled material in the repository: README, configuration examples, ADRs, migrations, tests, and release notes.
- Work through focused branches and pull requests. Keep `main` protected and releasable.

## Code review rules

- Block command injection, arbitrary CUPS options, path traversal, or files stored inside the public webroot.
- Block hardcoded printer capabilities without evidence from a fixture or live discovery.
- Require tests for CUPS parsers, option validation, upload validation, and job state transitions.
- Challenge structural complexity that does not serve a current use case.
