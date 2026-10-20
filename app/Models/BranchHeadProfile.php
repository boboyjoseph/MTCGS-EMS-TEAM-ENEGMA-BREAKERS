<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int|null $branch_id
 */
class BranchHeadProfile extends Model
{
    protected $table = 'branch_heads';
    
    protected $fillable = [
        'user_id', 'branch_id', 'employee_profile_id', 'employee_number', 'first_name', 'last_name', 'middle_name',
        'position', 'department', 'contact_number', 'address', 'date_hired',
        'fingerprint_template', 'is_fingerprint_registered'
    ];
    
    protected $casts = [
        'is_fingerprint_registered' => 'boolean',
        'date_hired' => 'date',
    ];
    
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /** @return BelongsTo<EmployeeProfile, $this> */
    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }
    
    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    /** @return HasMany<AttendanceLog, $this> */
    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'employee_profile_id', 'employee_profile_id');
    }
}
