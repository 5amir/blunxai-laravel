<?php

use Illuminate\Support\Facades\Schedule;
use Blunx\AI\Models\BlunxWidgetInsightSetting;
use Blunx\AI\Jobs\GenerateWidgetInsightJob;

// Hourly: dispatch a job for every due insight setting.
Schedule::call(function () {
    BlunxWidgetInsightSetting::due()
        ->with('widget')
        ->get()
        ->each(fn($setting) => GenerateWidgetInsightJob::dispatch($setting->id));
})->hourly()->name('blunx-insights');