<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// مزامنة أجهزة البصمة ومعالجة الحضور والانصراف تلقائياً كل 5 دقائق،
// فتظهر بصمة الموظف في السجل خلال دقائق من تسجيلها على الجهاز.
// (يتطلب مهمة Windows مجدولة تشغّل artisan schedule:run كل دقيقة)
Schedule::command('attendance:auto')->everyFiveMinutes()->withoutOverlapping();
