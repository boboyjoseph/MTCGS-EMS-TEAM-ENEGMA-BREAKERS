<?php

namespace App\Services;

use Carbon\Carbon;

class AttendanceTimeRules
{
    public static function lateMinutes(Carbon $actualTime, Carbon $scheduledStart, int $graceMinutes = 0): int
    {
        if (!$actualTime->gt($scheduledStart)) {
            return 0;
        }

        $lateSeconds = $scheduledStart->diffInSeconds($actualTime) - (max(0, $graceMinutes) * 60);
        if ($lateSeconds <= 0) {
            return 0;
        }

        return max(1, (int) ceil($lateSeconds / 60));
    }
}