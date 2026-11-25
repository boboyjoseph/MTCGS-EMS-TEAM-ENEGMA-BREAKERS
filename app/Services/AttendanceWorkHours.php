<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\SiteSetting;
use Carbon\Carbon;

class AttendanceWorkHours
{
    public const DEFAULT_START_TIME = '08:00:00';
    public const DEFAULT_END_TIME = '17:00:00';
    public const START_TIME_SETTING = 'attendance_default_start_time';
    public const END_TIME_SETTING = 'attendance_default_end_time';

    public static function startTime(?EmployeeProfile $profile = null): string
    {
        return $profile?->shift?->start_time
            ?: SiteSetting::getValue(self::START_TIME_SETTING, self::DEFAULT_START_TIME)
            ?: self::DEFAULT_START_TIME;
    }

    public static function endTime(?EmployeeProfile $profile = null): string
    {
        return $profile?->shift?->end_time
            ?: SiteSetting::getValue(self::END_TIME_SETTING, self::DEFAULT_END_TIME)
            ?: self::DEFAULT_END_TIME;
    }

    public static function scheduledStart(?EmployeeProfile $profile, ?Carbon $date = null): Carbon
    {
        return ($date?->copy() ?? Carbon::today())
            ->setTimeFromTimeString(self::startTime($profile));
    }

    public static function scheduledEnd(?EmployeeProfile $profile, ?Carbon $date = null): Carbon
    {
        return ($date?->copy() ?? Carbon::today())
            ->setTimeFromTimeString(self::endTime($profile));
    }
}
