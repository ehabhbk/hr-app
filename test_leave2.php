<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Leave;

$leave = Leave::find(14);
echo "from_date type: " . gettype($leave->from_date) . "\n";
echo "from_date class: " . get_class($leave->from_date) . "\n";
echo "from_date value: " . $leave->from_date . "\n";
echo "from_date->format('Y-m-d'): " . $leave->from_date->format('Y-m-d') . "\n";

$today = now()->toDateString();
echo "today: $today\n";
echo "today type: " . gettype($today) . "\n";

echo "\n--- Date comparisons ---\n";
echo "from_date <= today: " . ($leave->from_date <= $today ? 'true' : 'false') . "\n";
echo "from_date->format('Y-m-d') <= today: " . ($leave->from_date->format('Y-m-d') <= $today ? 'true' : 'false') . "\n";
echo "strval(from_date) <= today: " . (strval($leave->from_date) <= $today ? 'true' : 'false') . "\n";

echo "\n--- to_date comparisons ---\n";
echo "to_date: " . $leave->to_date . "\n";
echo "to_date >= today: " . ($leave->to_date >= $today ? 'true' : 'false') . "\n";
echo "to_date->format('Y-m-d') >= today: " . ($leave->to_date->format('Y-m-d') >= $today ? 'true' : 'false') . "\n";

echo "\n--- Full check ---\n";
$cond1 = $leave->status === 'approved';
$cond2 = $leave->to_date >= $today;
$cond3 = $leave->from_date <= $today;
echo "status === 'approved': " . ($cond1 ? 'true' : 'false') . "\n";
echo "to_date >= today: " . ($cond2 ? 'true' : 'false') . "\n";
echo "from_date <= today: " . ($cond3 ? 'true' : 'false') . "\n";
echo "all conditions: " . ($cond1 && $cond2 && $cond3 ? 'true' : 'false') . "\n";
