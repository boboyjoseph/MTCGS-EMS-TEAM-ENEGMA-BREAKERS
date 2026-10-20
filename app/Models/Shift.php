<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $fillable = [
        'name', 'class_code', 'room', 'start_time', 'end_time', 'break_start', 'break_end', 'working_days', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    /** @return HasMany<EmployeeProfile, $this> */
    public function employeeProfiles(): HasMany
    {
        return $this->hasMany(EmployeeProfile::class);
    }

    /** @return BelongsToMany<EmployeeProfile, $this> */
    public function assignedEmployees(): BelongsToMany
    {
        return $this->belongsToMany(EmployeeProfile::class, 'employee_shift_assignments');
    }

    public function getWorkingDaysListAttribute(): array
    {
        return array_filter(array_map('trim', explode(',', $this->working_days ?? '')));
    }
}
