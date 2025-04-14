<?php

use App\Console\Commands\PublishScheduledBlogs;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// schedule blog post published
Schedule::command(PublishScheduledBlogs::class)->everyMinute();