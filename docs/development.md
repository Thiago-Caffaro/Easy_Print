# Development

## Prerequisites

- PHP 8.5 with `intl`, `json`, `pdo`, and `pdo_sqlite`.
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

## Composition root

`config/bootstrap.php` creates the Slim application explicitly. There is no dependency container or service locator. Add a dependency only when a working vertical slice uses it and pass it through a constructor.

The initial route is server-rendered and can be checked in both supported locales:

- `http://127.0.0.1:8080/` for Portuguese;
- `http://127.0.0.1:8080/?lang=en` for English.

The explicit `lang` query parameter is the first locale-selection strategy. Invalid or disabled locale values fall back to the configured default. Account-based preferences are outside v1.0.
