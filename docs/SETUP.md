# Local setup

This guide covers two supported development profiles:

- **API-only:** MySQL plus the Laravel application; HTTP and activity auditing
  are disabled.
- **Full integration:** MySQL, Elasticsearch, queued audit indexing,
  Documentator, dashboards, and scheduled commands.

The repository does not include a Docker Compose or Sail service definition, so
the database and Elasticsearch services must already be reachable from the
host.

## Prerequisites

- PHP 8.3 or 8.4 with the extensions required by Laravel, MySQL, SQLite, and the
  Elasticsearch client.
- Composer 2.
- Node.js `^20.19.0` or `>=22.12.0` and npm.
- A MySQL database for normal local execution.
- Elasticsearch 9 for the full integration profile. Version 9 is the baseline
  exercised by the current dependency lock and CI service.

## Configure the environment

Clone the repository and create the local environment file:

```bash
git clone https://github.com/tsitsishvili/packages-testing-app.git
cd packages-testing-app
cp .env.example .env
```

Create the MySQL database named by `DB_DATABASE`, then update the `DB_*`
settings. Also replace the example service identity with stable values. Elastic
Audit stores this identity on every document and uses the index prefix when it
builds aliases:

```dotenv
APP_NAME="Packages Testing App - Your Name"

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=packages_testing_app
DB_USERNAME=root
DB_PASSWORD=

LOG_ELASTICSEARCH_INDEX_PREFIX=packages_testing_app_your_name
```

Do not share an `APP_NAME` or `LOG_ELASTICSEARCH_INDEX_PREFIX` with another
application writing to the same cluster.

### API-only profile

Disable both audit producers when Elasticsearch is not available:

```dotenv
HTTP_LOGS_ENABLED=false
ACTIVITY_LOGS_ENABLED=false
```

The API, Documentator generation commands, and PHPUnit suite remain usable.

### Full integration profile

Keep both audit producers enabled and configure the Elasticsearch connection:

```dotenv
HTTP_LOGS_ENABLED=true
ACTIVITY_LOGS_ENABLED=true

LOG_ELASTICSEARCH_HOST=localhost
LOG_ELASTICSEARCH_PORT=9200
LOG_ELASTICSEARCH_SCHEME=http
LOG_ELASTICSEARCH_USERNAME=
LOG_ELASTICSEARCH_PASSWORD=
```

The example environment retains HTTP and activity documents forever and
disables the ILM delete phase. Review those settings before sending real data;
see [Elastic Audit](ELASTIC_AUDIT.md#retention-and-redaction).

## Install the application

Run the one-time setup script after `.env` and the MySQL database are ready:

```bash
composer run setup
```

The script runs these tasks in order:

1. Installs Composer dependencies.
2. Keeps the existing `.env`, or copies `.env.example` when it is absent.
3. Generates `APP_KEY`.
4. Runs migrations with `--force`.
5. Installs npm dependencies.
6. Builds production frontend assets.

The default `DatabaseSeeder` is intentionally empty. Individual seeders create
large integration datasets and are not part of the quick-start flow.

## Initialize Elastic Audit

For the full profile, create the lifecycle policy before the indexes and
aliases:

```bash
php artisan elastic-audit:lifecycle-policy
php artisan http-logs:create-index
php artisan activity-logs:create-index
php artisan elastic-audit:health
```

The final command must succeed before audit capture is considered operational.
Use `php artisan elastic-audit:health --json` for machine-readable output.

## Run the development processes

Start the web server, database-backed queue listener, Pail, and Vite together:

```bash
composer run dev
```

This command does not run Laravel's scheduler. Start it separately when testing
statistics aggregation or the scheduled audit health check:

```bash
php artisan schedule:work
```

The schedule runs:

- `product:aggregate-statistics` every minute.
- `elastic-audit:health` every hour.

## Enable local API documentation

The interactive documentation is opt-in:

```dotenv
DOCUMENTATOR_ENABLED=true
DOCUMENTATOR_CACHE=false
```

Open `http://localhost:8000/docs`. Keep it disabled on untrusted deployments
until the permissive `Documentator::auth()` callback in `AppServiceProvider` is
replaced with application authorization.

## Register and authenticate

No demo user is seeded. Create one through the API:

```bash
curl --request POST http://localhost:8000/api/register \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "name": "Ada Lovelace",
    "email": "ada@example.com",
    "password": "secret-password",
    "password_confirmation": "secret-password"
  }'
```

The response contains a one-time plain-text Sanctum token. Send it on protected
requests as `Authorization: Bearer <token>`.

## Run checks

PHPUnit uses SQLite and the Elastic Audit test recorder, so MySQL and
Elasticsearch are not needed for the automated suite:

```bash
composer test
vendor/bin/pint --test
npm ci
npm run build
```

For API documentation checks, continue with
[API and Documentator](DOCUMENTATOR.md#verify-and-export-the-contract).
