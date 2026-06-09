<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$affected = \App\Models\AdvanceRequest::where('type', 'long')
    ->where('status', 'approved')
    ->where(function($q) {
        $q->whereNull('monthly_installment')
          ->orWhere('monthly_installment', 0);
    })
    ->where('installments', '>', 0)
    ->update([
        'monthly_installment' => \Illuminate\Support\Facades\DB::raw('amount / installments'),
        'remaining_amount' => \Illuminate\Support\Facades\DB::raw('amount')
    ]);

echo "Updated $affected long advances with monthly_installment\n";

// Also update short advances with remaining_amount = amount if null
$shortAffected = \App\Models\AdvanceRequest::where('type', 'short')
    ->where('status', 'approved')
    ->whereNull('remaining_amount')
    ->update(['remaining_amount' => \Illuminate\Support\Facades\DB::raw('amount')]);

echo "Updated $shortAffected short advances with remaining_amount\n";

echo "\nDone!";
