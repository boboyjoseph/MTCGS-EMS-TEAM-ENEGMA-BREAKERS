<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \Illuminate\Support\Carbon|null $corrected_pm_in
 * @property \Illuminate\Support\Carbon|null $corrected_time_out
 */
class AttendanceLog extends Model
{
    protected $table = 'attendance_logs';
    
    protected $fillable = [
        'employee_id', 'employee_profile_id', 'branch_id', 'attendance_date',
        'am_in', 'am_out', 'break_in', 'break_out', 'pm_in', 'pm_out',
        'status', 'late_minutes', 'overtime_hours', 'verification_method', 'device_mac_address', 'device_id', 'device_serial_number', 'location', 'override_status',
        'override_reason', 'corrected_time_in', 'corrected_pm_in', 'corrected_time_out', 'override_requested_by', 'override_reviewed_by', 'override_reviewed_at',
        'gps_latitude', 'gps_longitude', 'gps_accuracy', 'gps_timestamp'
    ];
    
    protected $casts = [
        'attendance_date' => 'date',
        'am_in' => 'datetime',
        'am_out' => 'datetime',
        'pm_in' => 'datetime',
        'pm_out' => 'datetime',
        'break_in' => 'datetime',
        'break_out' => 'datetime',
        'override_reviewed_at' => 'datetime',
        'corrected_time_in' => 'datetime',
        'corrected_pm_in' => 'datetime',
        'corrected_time_out' => 'datetime',
        'gps_timestamp' => 'datetime',
    ];
    
    /** @return BelongsTo<EmployeeProfile, $this> */
    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function isAfternoonOnlyAttendance(): bool
    {
        return $this->am_in
            && !$this->am_out
            && !$this->pm_in
            && $this->am_in->format('H:i:s') >= '13:00:00';
    }

    public function scopeExcludeAfternoonOnly($query)
    {
        return $query->where(function ($query) {
            $query->whereNull('am_in')
                ->orWhereTime('am_in', '<', '13:00:00')
                ->orWhereNotNull('am_out')
                ->orWhereNotNull('pm_in');
        });
    }

    public function scopeForEmployeeProfile(
        $query,
        int $employeeProfileId,
        ?int $userId = null,
        ?int $branchId = null
    )
    {
        return $query->where(function ($query) use ($employeeProfileId, $userId, $branchId) {
            $query->where('employee_profile_id', $employeeProfileId);

            if ($userId !== null || $branchId !== null) {
                $query->orWhere(function ($query) use ($employeeProfileId, $userId, $branchId) {
                    $query->whereNull('employee_profile_id')
                        ->where(function ($query) use ($employeeProfileId, $userId, $branchId) {
                            if ($userId !== null) {
                                $query->where('employee_id', $userId);
                            }

                            if ($branchId !== null) {
                                $query->orWhere(function ($query) use ($employeeProfileId, $branchId) {
                                    $query->where('employee_id', $employeeProfileId)
                                        ->where('branch_id', $branchId);
                                });
                            }
                        });
                });
            }
        });
    }

    public function scopeLate($query)
    {
        return $query->where('late_minutes', '>', 0)->excludeAfternoonOnly();
    }

    public function getDtrStatus(): string
    {
        $rawStatus = strtolower(trim((string) $this->status));
        if (in_array($rawStatus, ['leave', 'leave_paid', 'on leave'], true)) {
            return 'Leave';
        }
        if (in_array($rawStatus, ['absent', 'a'], true)) {
            return 'Absent';
        }

        $hasAnyAttendanceTime = (bool) ($this->am_in || $this->am_out || $this->pm_in || $this->pm_out);
        if (!$hasAnyAttendanceTime) {
            return 'Pending';
        }

        if ($this->override_status === 'approved') {
            return 'Present';
        }

        $hasFullAttendance = $this->am_in && $this->pm_in && $this->pm_out;
        $workedMinutes = 0;
        if ($this->am_in && $this->am_out) {
            $workedMinutes += $this->am_in->diffInMinutes($this->am_out);
        }
        if ($this->pm_in && $this->pm_out) {
            $workedMinutes += $this->pm_in->diffInMinutes($this->pm_out);
        }
        if (!$this->am_out && !$this->pm_in && $this->am_in && $this->pm_out) {
            $workedMinutes = $this->am_in->diffInMinutes($this->pm_out);
            if ($this->am_in->format('H:i:s') < '13:00:00' && $this->pm_out->format('H:i:s') > '12:00:00') {
                $workedMinutes = max(0, $workedMinutes - 60);
            }
        }

        $isSingleClockRange = !$this->am_out && !$this->pm_in && $this->am_in && $this->pm_out;
        $isAfternoonOnly = $this->isAfternoonOnlyAttendance();
        if ($isAfternoonOnly || (!$hasFullAttendance && (!$isSingleClockRange || $workedMinutes <= 240))) {
            return 'Half Day';
        }

        $profile = $this->employeeProfile;
        $scheduledStart = \App\Services\AttendanceWorkHours::scheduledStart($profile, $this->am_in);
        $scheduledEnd = \App\Services\AttendanceWorkHours::scheduledEnd($profile, $this->pm_out ?: $this->pm_in);
        $isLate = \App\Services\AttendanceTimeRules::lateMinutes($this->am_in, $scheduledStart) > 0;
        $isEarlyOut = (bool) $this->pm_out && $this->pm_out->lt($scheduledEnd);

        if ($isLate && $isEarlyOut) {
            return 'Late / Early Out';
        }
        if ($isLate) {
            return 'Late';
        }
        if ($isEarlyOut) {
            return 'Early Out';
        }

        return 'Present';
    }
    
    // Calculate total hours worked
    public function getTotalHoursAttribute()
    {
        $total = 0;
        
        if ($this->am_in && $this->am_out) {
            $total += $this->am_in->diffInHours($this->am_out);
        }
        if ($this->pm_in && $this->pm_out) {
            $total += $this->pm_in->diffInHours($this->pm_out);
        }
        
        return $total;
    }
    
    // Get formatted time for DTR
    public function getFormattedTime($column)
    {
        if ($this->$column) {
            return date('h:i A', strtotime($this->$column));
        }
        return '--:--';
    }
}
