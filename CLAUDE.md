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

Package-provided artisan commands worth knowing: `documentator:generate` / `documentator:export` / `documentator:postman` (API docs), and `elastic-audit`'s `*:create-*-index` and `*:prune-*` log index commands.

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

- **`tsitsishvili/documentator`** — generates interactive OpenAPI docs from routes/FormRequests/Resources, served at `/docs` for `api/*` routes (config: `config/documentator.php`). Access is gated by `Documentator::auth()`, currently wired open (`fn () => true`) in `AppServiceProvider::boot()` — tighten before production.
- **`tsitsishvili/elastic-audit`** — logs outgoing/incoming HTTP traffic and actor/model activity to a separate Elasticsearch cluster, with redaction, sampling, and dashboards at `/logger/*` (configs: `config/http_logs.php`, `config/activity_logs.php`, `config/log_elasticsearch.php`). The app must supply backed enums in `app/Enums/ElasticAudit/` implementing the package's `Provider`/`EventType`/`EntityType` contracts; these are referenced from `config/http_logs.php` under `enums`. Adding a `Provider` case requires bumping `HttpLogData::SCHEMA_VERSION` (noted in that enum).
