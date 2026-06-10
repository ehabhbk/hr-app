<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Leave;

$leave = Leave::find(14);
$now = now();
$today = $now->toDateString();

echo "now: " . $now . "\n";
echo "timezone: " . $now->timezone . "\n";
echo "today: $today\n\n";

echo "to_date raw: " . $leave->to_date . "\n";
echo "to_date format Y-m-d: " . $leave->to_date->format('Y-m-d') . "\n\n";

$toDate = \Carbon\Carbon::parse($leave->to_date->format('Y-m-d'));
echo "Parsed toDate: " . $toDate . "\n";
echo "now->startOfDay(): " . $now->copy()->startOfDay() . "\n";
echo "diffInDays: " . $toDate->diffInDays($now->copy()->startOfDay()) . "\n";
echo "diffInDays (absolute=false): " . $toDate->diffInDays($now->copy()->startOfDay(), false) . "\n";
echo "\nAlternative: \n";
echo "toDate->diffInDays(Carbon::parse($today)): " . $toDate->diffInDays(\Carbon\Carbon::parse($today)) . "\n";
echo "toDate->floatDiffInDays(Carbon::parse($today)): " . $toDate->floatDiffInDays(\Carbon\Carbon::parse($today)) . "\n";
