<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SLA công việc: nhắc theo remind_at + cảnh báo quá hạn (idempotent, chống trùng).
Schedule::command('tasks:dispatch-reminders')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
