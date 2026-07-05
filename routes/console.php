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
