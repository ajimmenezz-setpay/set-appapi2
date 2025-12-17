<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::call(function () {
    DB::transaction(function () {
        DB::table('smtp_accounts')->update([
            'sent_today' => 0,
            'is_next' => false,
            'updated_at' => now(),
        ]);

        DB::table('smtp_accounts')
            ->where('active', true)
            ->where(function ($q) {
                $q->whereNull('disabled_until')
                    ->orWhere('disabled_until', '<=', now());
            })
            ->orderBy('id')
            ->limit(1)
            ->update([
                'is_next' => true,
                'updated_at' => now(),
            ]);
    });
})
    ->dailyAt('00:00')
    ->timezone('America/Mexico_City');


Schedule::call(function () {
    DB::table('smtp_accounts')
        ->whereNotNull('disabled_until')
        ->where('disabled_until', '<=', now())
        ->update([
            'active' => 1,
            'disabled_until' => null,
            'fail_count' => 0,
            'last_error' => null,
            'updated_at' => now(),
        ]);
})
    ->everyMinute();
