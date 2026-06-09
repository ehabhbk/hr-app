<?php

namespace App\Console\Commands;

use App\Models\Leave;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SendLeaveReminders extends Command
{
    protected $signature = 'notifications:leave-reminders';
    protected $description = 'Send reminders to employees one day before their leave ends';

    public function handle()
    {
        $tomorrow = Carbon::tomorrow()->format('Y-m-d');
        
        $leaves = Leave::where('status', 'approved')
            ->where('end_date', $tomorrow)
            ->with('employee')
            ->get();

        $whatsapp = new WhatsAppService();

        foreach ($leaves as $leave) {
            if ($leave->employee && $leave->employee->phone) {
                $whatsapp->sendLeaveReminder($leave->employee, $leave);
                $this->info("Sent leave reminder to {$leave->employee->name}");
            }
        }

        $this->info("Leave reminders sent: {$leaves->count()}");
    }
}
