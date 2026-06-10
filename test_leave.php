<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Employee;
use App\Models\Leave;

$emp = Employee::with('leaves')->find(19);
echo "Employee: " . $emp->name . "\n";
echo "Status: " . $emp->status . "\n";
echo "Leave count (col): " . $emp->leave_count . "\n";

$leaves = $emp->leaves ?? collect();
echo "Leaves loaded: " . $leaves->count() . "\n";

foreach ($leaves as $l) {
    echo "  Leave: id={$l->id} type={$l->type} status={$l->status} from={$l->from_date} to={$l->to_date}\n";
}

// Also try directly via Leave model
$directLeaves = Leave::where('employee_id', 19)->get();
echo "Direct leaves query: " . $directLeaves->count() . "\n";
foreach ($directLeaves as $l) {
    echo "  Leave: id={$l->id} type={$l->type} status={$l->status} from={$l->from_date} to={$l->to_date}\n";
}

// Check active leave calculation
$today = now()->toDateString();
echo "Today: $today\n";
$activeLeave = null;
foreach ($leaves as $l) {
    if ($l->status === 'approved' && $l->to_date >= $today && $l->from_date <= $today) {
        $activeLeave = $l;
        echo "Active leave found: type={$l->type}\n";
        break;
    }
}
if (!$activeLeave) {
    echo "No active leave found\n";
    // Debug dates
    foreach ($leaves as $l) {
        echo "  Check: status={$l->status} to_date={$l->to_date} >= $today ? " . ($l->to_date >= $today ? 'true' : 'false') . " from_date={$l->from_date} <= $today ? " . ($l->from_date <= $today ? 'true' : 'false') . "\n";
    }
}
