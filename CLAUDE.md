# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Laravel 12 / PHP 8.3 application. The one real domain feature is a **product statistics aggregation pipeline**; the rest is a stock Laravel skeleton plus two first-party Composer packages (`tsitsishvili/documentator`, `tsitsishvili/elastic-audit`). There is no front-end to speak of (Vite + Tailwind are present but unused beyond the welcome page).

## Commands

```bash
composer dev          # run server + queue worker + log tailer (pail) + vite concurrently
composer test         # clears config, then runs the full test suite
composer setup        # first-time bootstrap: install, .env, key, migrate, npm build

php artisan test --filter test_it_aggregates_add_to_cart_statistics   # single test
php artisan test tests/Feature/StatisticsAggregationTest.php          # single file

php artisan migrate
php artisan product:aggregate-statistics      # the core aggregation command (also scheduled)

./vendor/bin/pint     # format / lint (Laravel preset, no custom config)

npm run dev           # vite dev server only
npm run build
```

Package-provided artisan commands worth knowing: `documentator:generate` / `documentator:export` / `documentator:postman` / `documentator:check` (API docs; `check` audits generated docs for gaps/drift), and `elastic-audit`'s `*:create-*-index` and `*:prune-*` log index commands.

## Database

- **Dev/prod run on MySQL** (`.env` default). **Tests run on in-memory SQLite** (`phpunit.xml`).
- This split matters: `ProductStatisticsRepository::upsert()` uses MySQL-specific `column + VALUES(column)` raw SQL for incremental counts, and `product_statistics` uses `ON UPDATE CURRENT_TIMESTAMP`. The test suite does **not** exercise the repository (tests call the service directly), so this MySQL-only code is unverified by CI — be careful changing it.
- Queue and cache both use the `database` driver in dev; tests force `sync` queue and `array` cache.

## Architecture: statistics pipeline

The flow is event-collection → periodic aggregation → incremental upsert.

1. **Raw events** land in three append-only temp tables: `temp_product_view_events`, `temp_product_appearance_events`, `temp_product_add_to_cart_events` (models `App\Models\TempProduct*Events`, `$timestamps = false`). Each row is `(product_id, user_id?)`.

2. **`product:aggregate-statistics`** (`app/Console/Commands/AggregateProductStatistics.php`) is scheduled `everyMinute()->withoutOverlapping()` in `routes/console.php`. It calls `StatisticAggregationService`, stamps each row with today's `event_date`, and hands off to `ProductStatisticsRepository::upsert()`.

3. **`StatisticAggregationService`** (`app/Services/Statistics/`) fans out over a list of **`MetricAggregator`** implementations and merges their output into one row per `product_id`, defaulting any metric a product lacks to `0`. The aggregator list is **injected**, not auto-discovered.

4. **Counters** (`app/Services/Statistics/Counters/*`) are the `MetricAggregator` implementations — one per metric group (`ViewsCounter`, `AppearanceCounter`, `AddToCartCounter`). Each runs a `GROUP BY product_id` query over its temp table and declares its output column names via `metrics()`.

5. **`ProductStatisticsRepository::upsert()`** chunks (1000 rows) and incrementally adds counts onto the existing `(product_id, event_date)` row.

### Adding a new metric

This is the most common change. To add one:
1. Create a `Counter` in `app/Services/Statistics/Counters/` implementing `MetricAggregator` (`calculate()` returns a Collection keyed by `product_id`; `metrics()` lists the column names).
2. Register it in the `StatisticAggregationService` binding in **`app/Providers/AppServiceProvider::register()`** — the list there is the single source of truth for which aggregators run.
3. Add matching columns to `product_statistics` (migration) and `ProductStatistic::$fillable`.

> Note: `App\Jobs\TrackProductEvent` and `Product::events()` reference an `App\Models\ProductEvent` / `product_events` table that **does not exist** (no model, no migration). This is dead/legacy code — events are actually written straight to the temp tables. Don't rely on it.

## First-party packages

Both packages have local checkouts under `../../packages/<name>` (sibling `Desktop/packages/` dir). To develop against a checkout instead of the published release, add a Composer `path` repository (`symlink: true`) for it and require the branch's dev version, then `composer update <pkg>`. Gotcha: git branch `v1.x` normalizes to Composer version **`1.x-dev`** (not `dev-v1.x`), and you must require that exact dev version — a stable constraint like `^1.6` re-selects the published release from git/Packagist over the symlinked path. Verify with `ls -la vendor/tsitsishvili/<name>` (should be a symlink).

- **`tsitsishvili/documentator`** — generates interactive OpenAPI docs from routes/FormRequests/Resources, served at `/docs` for `api/*` routes (config: `config/documentator.php`). Access is gated by `Documentator::auth()`, currently wired open (`fn () => true`) in `AppServiceProvider::boot()` — tighten before production.
- **`tsitsishvili/elastic-audit`** — logs outgoing/incoming HTTP traffic and actor/model activity to a separate Elasticsearch cluster, with redaction, sampling, and dashboards at `/logger/*` (configs: `config/http_logs.php`, `config/activity_logs.php`, `config/log_elasticsearch.php`). The app must supply backed enums in `app/Enums/ElasticAudit/` implementing the package's `Provider`/`EventType`/`EntityType` contracts; these are referenced from `config/http_logs.php` under `enums`. Adding a `Provider` case requires bumping `HttpLogData::SCHEMA_VERSION` (noted in that enum).

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== tsitsishvili/documentator rules ===

## Documentator

This application uses `tsitsishvili/documentator` to infer **OpenAPI 3.2** from
Laravel code. Keep documentation code-first: prefer typed routes, FormRequests,
validation, Resources/Data objects, and return types; use attributes only for
facts inference cannot see.

When changing an API endpoint:

- Inspect `config/documentator.php`, the route/middleware, action, request type,
  response type/expression, and tests.
- Put the first summary paragraph and following description in the method docblock.
- On `GET`/`HEAD`, validation becomes URI query parameters, not a request body.
- For HTTP `QUERY`, use `Route::match(['QUERY'], ...)`; validation remains request
  content and becomes the OpenAPI 3.2 `query.requestBody`.
- Do not confuse HTTP `QUERY` request content with URI query parameters.
- Add `Tsitsishvili\Documentator\Attributes` only for gaps or intentional overrides.
- Verify with `php artisan documentator:explain METHOD /uri` and
  `php artisan documentator:check`.

Use the **`documentator-api-docs`** skill for the full workflow, inference map,
attribute guidance, troubleshooting, contract checks, and examples.

=== tsitsishvili/elastic-audit rules ===

## Elastic Audit

Elastic Audit records third-party HTTP traffic and actor/model activity in a dedicated Elasticsearch cluster. HTTP
logs and activity logs are independent subsystems with separate configuration, queues, indexes, and dashboards.

- Inspect `config/http_logs.php`, `config/activity_logs.php`, and `config/log_elasticsearch.php` before changing an
  integration. Never edit the package files under `vendor/`.
- Use `HttpLog::make(...)` instead of Laravel's `Http` facade when an outgoing provider request must be audited. It
  returns an `Illuminate\Http\Client\PendingRequest`, so the normal Laravel HTTP client API remains available.
- Pass existing backed enum cases implementing `ProviderContract`, `EventTypeContract`, and `EntityTypeContract` to
  HTTP logging APIs. Inspect the consuming application's registered enum classes and never invent enum cases.
- For incoming callbacks, use `IncomingHttpLogMiddleware` and set `third_party_*` request attributes from trusted
  application code. Never derive provider, event, or entity types from user-controlled request input.
- Use `ActivityLog::record(...)` for explicit domain events and `ActivityLoggable` for automatic Eloquent lifecycle
  events. Activity entity and actor types are free string labels.
- Logging dispatches queued jobs. Keep the configured queue worker running and use `Bus::fake()` when asserting job
  dispatch in tests; unit tests should not require a live Elasticsearch cluster.
- Review redaction before capturing new headers, fields, or metadata. Treat every `redaction.allow` entry as a security
  exception because allowed values are stored in clear text.
- After infrastructure or configuration changes, run `php artisan elastic-audit:health --all`. Install the lifecycle
  policy before creating HTTP or activity indexes on a fresh environment.

<!-- Outgoing audited request -->
```php
$response = HttpLog::make(
    provider: Provider::Delivery,
    eventType: EventType::DeliveryOrderCreate,
    context: HttpLogContext::forEntity(
        entityType: EntityType::Order,
        entityId: (string) $order->getKey(),
    ),
)->post($url, $payload);
```

</laravel-boost-guidelines>
