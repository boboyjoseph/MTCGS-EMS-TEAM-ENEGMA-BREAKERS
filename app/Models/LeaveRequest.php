<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    protected $fillable = [
        'employee_id', 'employee_profile_id', 'leave_type', 'start_date', 'end_date', 
        'total_days', 'reason', 'status', 'is_absent', 'approval_remarks', 'approved_at',
        'branch_approved_by', 'branch_approved_at', 'system_admin_approved_by', 'system_admin_approved_at'
    ];
    
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'is_absent' => 'boolean',
        'branch_approved_at' => 'datetime',
        'system_admin_approved_at' => 'datetime',
    ];
    
    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
    
    /** @return BelongsTo<EmployeeProfile, $this> */
    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }
    
    /** @return BelongsTo<LeaveBalance, $this> */
    public function leaveBalance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class, 'employee_profile_id', 'employee_profile_id')
            ->where('year', date('Y'));
    }
}
