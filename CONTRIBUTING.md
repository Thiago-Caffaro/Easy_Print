# Contributing to Easy Print

Thank you for helping improve Easy Print. The project favors small, reviewable changes tied to an agreed outcome.

## Before starting

1. Search existing Issues and the Project backlog.
2. Open or select an Issue with clear acceptance criteria.
3. Discuss architecture or scope changes before implementation.
4. Do not use a physical printer in an automated or manual test without explicit authorization.

## Development workflow

- Branch from the latest `main` using `feature/<issue>-<slug>`, `fix/<issue>-<slug>`, or `docs/<issue>-<slug>`.
- Keep one cohesive outcome per pull request.
- Use English for code, comments, commits, Issues, pull requests, and documentation.
- Keep Portuguese and English translation catalogs synchronized when user-facing copy changes.
- Follow PSR-12, declare strict types, and prefer immutable value objects at domain boundaries.
- Add or update tests with behavior changes.
- Update README, ADRs, or Wiki material when a change alters use, architecture, or operations.

## Required checks

The exact commands will be added with the foundation scaffold. The intended quality gate includes:

- Composer validation and security audit.
- Code formatting.
- Static analysis.
- Unit and integration tests.
- Container build.
- Markdown and configuration validation.

Never report a check as passing unless it was executed.

## Pull requests

Every pull request should:

- Link its Issue.
- Explain what changed and why.
- Include automated and manual verification evidence.
- Identify security, localization, data-retention, and CUPS compatibility impact.
- Avoid unrelated cleanup.
- Remain draft until the implementation and checks are ready for review.

## Architecture expectations

- CUPS remains the source of truth for queues, capabilities, and active jobs.
- The web application does not administer CUPS or access printers directly.
- Printer-specific behavior must come from discovered capabilities and tested fixtures.
- Shell commands must use allowlisted executables and separated, validated arguments.
- Uploaded documents must remain outside the webroot and be deleted after spool acceptance.

## Reporting security issues

Do not open public Issues for vulnerabilities. Follow [SECURITY.md](SECURITY.md).
