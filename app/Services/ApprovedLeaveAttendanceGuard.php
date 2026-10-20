<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\LeaveRequest;
use Carbon\Carbon;

class ApprovedLeaveAttendanceGuard
{
    public function blockedReason(EmployeeProfile $employee, Carbon|string $date): ?string
    {
        $day = $date instanceof Carbon
            ? $date->copy()
            : Carbon::parse($date, config('app.timezone'));

        if (!app(WorkingDayService::class)->isWorkingDay($day, $employee->branch_id)) {
            return null;
        }

        $leave = LeaveRequest::where('employee_profile_id', $employee->id)
            ->whereIn('status', ['pending', 'pending_system_admin', 'approved'])
            ->whereDate('start_date', '<=', $day->toDateString())
            ->whereDate('end_date', '>=', $day->toDateString())
            ->latest('id')
            ->first();

        if (!$leave) {
            return null;
        }

        if ($leave->status !== 'approved') {
            return 'Attendance is not allowed because the employee has a leave request for this date.';
        }

        return $leave->is_absent
            ? 'Attendance is not allowed because the employee is on leave without pay.'
            : 'Attendance is not allowed because the employee is on paid leave.';
    }
}
