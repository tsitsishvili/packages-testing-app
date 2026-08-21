<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('product:aggregate-statistics')
    ->everyMinute()
    ->withoutOverlapping();

// Validate Elasticsearch connectivity, aliases, lifecycle, enum, and queue job
// config for elastic-audit. Fails loudly (non-zero exit) so a broken logging
// pipeline surfaces instead of silently dropping audit events.
Schedule::command('elastic-audit:health')
    ->hourly()
    ->withoutOverlapping();

/*
 * Metrics and profiles are the only elastic-audit subsystems with finite
 * retention here (HTTP and activity documents are permanent). ILM's delete phase
 * is off cluster-wide to protect those permanent documents, so per-document
 * pruning is what actually reclaims APM storage.
 */
Schedule::command('elastic-audit:metrics:prune')
    ->dailyAt('03:10')
    ->withoutOverlapping();

Schedule::command('elastic-audit:profiles:prune')
    ->dailyAt('03:20')
    ->withoutOverlapping();
