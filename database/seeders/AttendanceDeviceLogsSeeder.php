<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceDeviceLogsSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        DB::table('attendance_device_logs')->insert([
            [
                'uid'            => 1,
                'device_user_id' => 'EMP001',
                'state'          => 1,
                'timestamp'      => $now->subMinutes(10),
                'raw'            => json_encode(['uid' => 1, 'id' => 'EMP001', 'state' => 1, 'timestamp' => $now->subMinutes(10)->toDateTimeString()]),
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'uid'            => 2,
                'device_user_id' => 'EMP002',
                'state'          => 0,
                'timestamp'      => $now->subMinutes(5),
                'raw'            => json_encode(['uid' => 2, 'id' => 'EMP002', 'state' => 0, 'timestamp' => $now->subMinutes(5)->toDateTimeString()]),
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
        ]);
    }
}