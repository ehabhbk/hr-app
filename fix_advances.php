<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Fix existing advances with null remaining_amount
$affected = \App\Models\AdvanceRequest::whereNull('remaining_amount')
    ->where('status', 'approved')
    ->update(['remaining_amount' => \Illuminate\Support\Facades\DB::raw('amount')]);

echo "Updated $affected advances with remaining_amount = amount\n";

// Also check if monthly_installment needs to be set
$advancesNoInstallment = \App\Models\AdvanceRequest::whereNull('monthly_installment')
    ->where('status', 'approved')
    ->where('type', 'long')
    ->where('installments', '>', 1)
    ->get();

foreach ($advancesNoInstallment as $adv) {
    $installment = $adv->remaining_amount / max($adv->installments, 1);
    $adv->update(['monthly_installment' => $installment]);
    echo "Set monthly_installment for advance #{$adv->id} to $installment\n";
}

echo "\nDone!\n";
