<?php

use App\Services\AttendanceWorkHours;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('site_settings')->insertOrIgnore([
            [
                'key' => AttendanceWorkHours::START_TIME_SETTING,
                'value' => AttendanceWorkHours::DEFAULT_START_TIME,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => AttendanceWorkHours::END_TIME_SETTING,
                'value' => AttendanceWorkHours::DEFAULT_END_TIME,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('site_settings')
            ->whereIn('key', [
                AttendanceWorkHours::START_TIME_SETTING,
                AttendanceWorkHours::END_TIME_SETTING,
            ])
            ->delete();
    }
};
