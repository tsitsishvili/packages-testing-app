# Packages Testing App

[![CI](https://github.com/tsitsishvili/packages-testing-app/actions/workflows/ci.yml/badge.svg)](https://github.com/tsitsishvili/packages-testing-app/actions/workflows/ci.yml)
[![CodeQL](https://github.com/tsitsishvili/packages-testing-app/actions/workflows/codeql.yml/badge.svg)](https://github.com/tsitsishvili/packages-testing-app/actions/workflows/codeql.yml)

Packages Testing App is a Laravel 13 integration and reference application for
[`tsitsishvili/documentator`](https://github.com/tsitsishvili/documentator) and
[`tsitsishvili/elastic-audit`](https://github.com/tsitsishvili/elastic-audit).
It exercises the packages against real authentication, product, order,
statistics, webhook, queue, and scheduler flows.

> This repository tracks development versions of both first-party packages and
> deliberately exposes integration fixtures. Treat it as a test bed, not a
> production-ready application template.

## What it demonstrates

- OpenAPI 3.2 generation from Laravel routes, Form Requests, API Resources,
  Eloquent models, Spatie Data objects, Spatie Query Builder, and Laravel
  Actions.
- Unversioned and `/api/v2` product APIs, including separate documentation
  sections.
- Sanctum personal access tokens for protected API operations.
- Standard URI query parameters alongside an HTTP `QUERY` operation with a
  structured request body.
- Outgoing and incoming HTTP auditing, Eloquent activity auditing, redaction,
  queues, Elasticsearch indexes, and operator dashboards.
- Scheduled product-statistics aggregation with an explicit domain audit event.

## Requirements

- PHP 8.3 or 8.4 and Composer 2.
- Node.js `^20.19.0` or `>=22.12.0` and npm.
- MySQL for the default local configuration. The test suite uses SQLite.
- Elasticsearch 9 for the full Elastic Audit profile used by this lock file and
  CI. It is optional when both audit subsystems are disabled.

## Quick start

```bash
git clone https://github.com/tsitsishvili/packages-testing-app.git
cd packages-testing-app
cp .env.example .env
```

Before running setup, create the configured MySQL database and review `.env`.
In particular, choose a stable `APP_NAME` and `LOG_ELASTICSEARCH_INDEX_PREFIX`.
For an API-only setup without Elasticsearch, disable both audit subsystems:

```dotenv
HTTP_LOGS_ENABLED=false
ACTIVITY_LOGS_ENABLED=false
```

Install the PHP and frontend dependencies, generate the application key, run
migrations, and build the frontend assets:

```bash
composer run setup
```

Start the application server, queue listener, Pail, and Vite:

```bash
composer run dev
```

The application is then available at `http://localhost:8000`. The setup script
does not seed demo records; register through `POST /api/register` to obtain a
Sanctum bearer token.

For scheduled statistics aggregation and audit health checks, run this in a
separate terminal:

```bash
php artisan schedule:work
```

See [Local setup](docs/SETUP.md) for the complete API-only and Elasticsearch
workflows.

## API documentation

Documentator is disabled by default. For local development, set:

```dotenv
DOCUMENTATOR_ENABLED=true
DOCUMENTATOR_CACHE=false
```

The documentation landing page is then available at
`http://localhost:8000/docs`, with separate API and API v2 sections. The access
callback is currently permissive, so do not enable this surface on an untrusted
deployment without replacing the callback in `AppServiceProvider`.

Read [API and Documentator](docs/DOCUMENTATOR.md) for the documented surfaces,
code-first inference map, generation commands, and verification workflow.

## Elastic Audit

The example environment enables HTTP logs, activity logs, queues, dashboards,
and permanent retention. Initialize a reachable Elasticsearch cluster before
using that profile, or disable the two audit subsystems as shown above.

Read [Elastic Audit](docs/ELASTIC_AUDIT.md) for connection settings, index
initialization, retention, redaction, dashboards, and operational commands.

## Tests and quality checks

The application tests do not require MySQL or a live Elasticsearch cluster:

```bash
composer test
vendor/bin/pint --test
npm ci
npm run build
```

Audit and export the generated API contract with:

```bash
php artisan documentator:check
php artisan documentator:generate
php artisan documentator:export openapi.json
```

## Security

Do not report vulnerabilities in public issues. Follow the private reporting
process and deployment notes in [SECURITY.md](SECURITY.md).

## License

This project is licensed under the [MIT License](LICENSE).
