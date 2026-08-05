# Elastic Audit

This application integrates `tsitsishvili/elastic-audit` in two independent
subsystems:

- **HTTP logs** capture selected outgoing provider traffic and incoming
  callbacks.
- **Activity logs** capture model lifecycle changes and explicit domain events.

Both subsystems dispatch queued indexing jobs to a dedicated Elasticsearch
cluster. Enabling one does not enable the other.

## Development profiles

The checked-in `.env.example` is a full integration profile: both subsystems and
dashboards are enabled, documents are retained forever, and the ILM delete phase
is disabled. That profile requires a reachable Elasticsearch cluster and a
running queue worker.

For API work without Elasticsearch, disable both producers:

```dotenv
HTTP_LOGS_ENABLED=false
ACTIVITY_LOGS_ENABLED=false
```

## Service identity and connection

Give every application writing to a shared cluster a stable identity and index
prefix:

```dotenv
APP_NAME="Packages Testing App - Your Name"
APP_ENV=local

LOG_ELASTICSEARCH_HOST=localhost
LOG_ELASTICSEARCH_PORT=9200
LOG_ELASTICSEARCH_SCHEME=http
LOG_ELASTICSEARCH_USERNAME=
LOG_ELASTICSEARCH_PASSWORD=
LOG_ELASTICSEARCH_INDEX_PREFIX=packages_testing_app_your_name
```

The current lock file and CI integration job use Elasticsearch 9.0.0. Match the
cluster major version to the installed Elasticsearch PHP client.

## Initialize Elasticsearch

Install the lifecycle policy before creating the indexes so new indexes receive
the intended rollover and retention configuration:

```bash
php artisan elastic-audit:lifecycle-policy
php artisan http-logs:create-index
php artisan activity-logs:create-index
php artisan elastic-audit:health
```

Use `php artisan elastic-audit:health --json` in deployment automation. Add
`--all` only when aliases for disabled subsystems have also been provisioned and
must be checked.

## Queues and schedules

Audit capture dispatches queued jobs. `composer run dev` includes a database
queue listener; production needs a supervised worker for each configured audit
queue.

`php artisan schedule:work` runs the application schedule locally. The schedule
includes an hourly `elastic-audit:health` check and the per-minute product
statistics aggregation command.

## Captured application flows

| Flow | Capture path |
| --- | --- |
| Product catalog sync | `HttpLog::make(...)` wraps the outgoing JSONPlaceholder request |
| Demo payment callback | `IncomingHttpLogMiddleware` records the incoming webhook after trusted request attributes are set |
| Product and order changes | `ActivityLoggable` records Eloquent lifecycle events |
| Product statistics aggregation | `ActivityLog::record(...)` records the query-builder upsert as an explicit domain event |

The payment callback is an integration fixture, not a production Stripe
handler: it does not verify signatures and deliberately simulates success and
failure metadata.

## Dashboards

With the example paths and dashboards enabled:

- HTTP logs: `http://localhost:8000/logger/third-party`
- Activity logs: `http://localhost:8000/logger/activity`

The package's default dashboard authorization is local-environment only. Replace
it with application authorization before exposing either dashboard elsewhere;
captured request, response, actor, and model data may be sensitive.

## Retention and redaction

The example environment sets both `HTTP_LOGS_RETAIN_FOREVER` and
`ACTIVITY_LOGS_RETAIN_FOREVER` to `true`, and sets
`LOG_ELASTICSEARCH_LIFECYCLE_DELETE_ENABLED=false`. This is suitable only when
permanent storage is intentional.

For finite retention, use subsystem retention-day values and enable an ILM
delete phase that matches the operational policy. A finite `retentionDays`
value must be between 1 and 32767; never combine it with `retainForever: true`.

Review `config/http_logs.php` and `config/activity_logs.php` before capturing new
fields. Built-in redaction covers common secrets, and application `block` lists
can add protected names. Every `allow` entry is a security exception because
the value is stored in clear text.

Undecodable bodies default to metadata plus a hash. Setting
`HTTP_LOGS_UNDECODABLE_BODY_MODE=preview` stores raw preview content and requires
an explicit security review. Oversized bodies are captured headers-only.

## Operations

Check health after configuration or infrastructure changes:

```bash
php artisan elastic-audit:health
```

Run rollover and per-document pruning when required by the chosen lifecycle
strategy:

```bash
php artisan http-logs:rollover
php artisan activity-logs:rollover
php artisan http-logs:prune
php artisan activity-logs:prune
```

Permanent documents are ignored by prune commands. ILM works at the whole-index
level and can still delete them unless its delete phase is disabled.
