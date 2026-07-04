<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('product:aggregate-statistics')
    ->everyMinute()
    ->withoutOverlapping();
