<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('attendance:sync')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->call(function () {
            $controller = new \App\Http\Controllers\AttendanceRecordController();
            $request = new \Illuminate\Http\Request();
            $request->merge([
                'from_date' => now()->format('Y-m-d'),
                'to_date' => now()->format('Y-m-d'),
            ]);
            $controller->processFromDeviceLogs($request);
        })->everyFiveMinutes()->withoutOverlapping();

        $schedule->command('notifications:leave-reminders')
            ->dailyAt('09:00')
            ->withoutOverlapping();
            
        $schedule->command('attendance:check-absences')
            ->dailyAt('18:00')
            ->withoutOverlapping();

        $schedule->call(function () {
            $expiredLeaves = \App\Models\Leave::where('status', 'approved')
                ->where('to_date', '<', now()->toDateString())
                ->get();

            foreach ($expiredLeaves as $leave) {
                $employee = \App\Models\Employee::find($leave->employee_id);
                if ($employee && $employee->status === 'vacation') {
                    $hasActiveLeaves = \App\Models\Leave::where('employee_id', $employee->id)
                        ->where('status', 'approved')
                        ->where('to_date', '>=', now()->toDateString())
                        ->exists();

                    if (!$hasActiveLeaves) {
                        $employee->status = 'active';
                        $employee->save();
                    }
                }
            }
        })->dailyAt('00:05')->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
