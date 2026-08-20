# Development

## Prerequisites

- PHP 8.5 with `fileinfo`, `gd`, `intl`, `json`, `pdo`, and `pdo_sqlite`.
- Composer 2.10 or newer.
- Node.js 24 for the pinned Markdown lint command.
- Docker Engine with the Compose plugin for the Linux reference package.

The supported runtime is Linux AMD64. Running PHP tools on another operating system is useful for fast feedback but does not replace container validation.

## Install and run

```bash
composer install
cp .env.example .env
php bin/migrate.php
php -S 127.0.0.1:8080 -t public public/router.php
```

The application reads environment variables directly. The PHP development server does not load `.env`; export the values in the shell or use Docker Compose when defaults are insufficient.

The bare development command above does not inject PHP upload directives. Docker Compose uses the production entrypoint and is the reference for verifying `post_max_size`, `upload_max_filesize`, and `max_file_uploads`. Application-level request limits remain active in both modes.

## Quality gate

```bash
composer check
npx --yes markdownlint-cli2@0.23.2
docker compose config --quiet
docker compose build web cups
```

Focused commands are available as Composer scripts:

| Command | Purpose |
| --- | --- |
| `composer validate:composer` | Validate `composer.json` and lock-file consistency |
| `composer audit:dependencies` | Check locked packages against security advisories |
| `composer format` | Apply the PHP formatting rules |
| `composer format:check` | Check formatting without modifying files |
| `composer analyse` | Run PHPStan at level 8 |
| `composer test:unit` | Run pure unit tests |
| `composer test:integration` | Run HTTP, SQLite, and process-boundary tests |
| `composer test` | Run the complete PHPUnit suite |

Normal tests never contact CUPS or a physical printer. Real printer checks require explicit authorization and recorded environment details.

HTMX is pinned in `package-lock.json` and copied into the public asset directory so private deployments do not depend on a CDN. After changing the locked version, regenerate and verify the committed files:

```bash
npm ci --ignore-scripts
npm run assets:sync
npm run assets:verify
```

The opt-in, read-only queue discovery smoke check is:

```bash
php bin/check-cups.php
```

It uses the configured CUPS host and exits non-zero for unavailable, unauthorized, timed-out, or malformed responses. See [CUPS queue discovery](cups-discovery.md) for its result contract and Docker invocation.

## Composition root

`config/bootstrap.php` creates the Slim application explicitly. There is no dependency container or service locator. Add a dependency only when a working vertical slice uses it and pass it through a constructor.

The initial route is server-rendered and can be checked in both supported locales:

- `http://127.0.0.1:8080/` for Portuguese;
- `http://127.0.0.1:8080/?lang=en` for English.

The explicit `lang` query parameter is the first locale-selection strategy. Invalid or disabled locale values fall back to the configured default. Account-based preferences are outside v1.0.

## Print page

`/` renders a conventional multipart print form without requiring a JavaScript framework. It offers PDF, PNG, and JPEG uploads, copies, an optional CUPS page range, and only the normalized capability controls currently advertised by the selected queue.

Changing the queue is progressively enhanced with HTMX through `GET /print-form`; the response replaces only the form controls and refreshes the capability fingerprint. Without JavaScript, the queue links on the page remain available for selecting a queue before submitting.

`POST /print` is CSRF-protected. It rechecks the submitted queue against current CUPS discovery, validates the capability fingerprint and selected values, validates and privately stages the upload, then calls the existing CUPS submission use case. The response contains only a safe outcome and optional CUPS job identifier; private document bytes are deleted after the submission attempt.

Active jobs are rendered at `/jobs/active`. Eligible pending and processing jobs expose a CSRF-protected `POST /jobs/{queue}/cancel/{job}` control. Cancellation is verified against a fresh CUPS snapshot before the command and reconciled with a second snapshot afterward; outages and stale jobs produce conservative localized feedback instead of unsafe retries.

Every future `POST`, `PUT`, `PATCH`, or `DELETE` route is protected automatically. Server-rendered forms must use the `easy_print.csrf_token` request attribute in a hidden `_csrf` field. HTMX requests may send the same value in `X-CSRF-Token`. Do not exempt mutation routes; a read-only health endpoint must use `GET` or `HEAD`.
