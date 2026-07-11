<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\MonitoredUrl;
use App\Jobs\UrlCheckJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

//  这里是新添加的自动化监控调度
Schedule::call(function () {
    MonitoredUrl::where('is_active', true)->each(function ($url) {
        UrlCheckJob::dispatch($url);
    });
})->everyMinute();