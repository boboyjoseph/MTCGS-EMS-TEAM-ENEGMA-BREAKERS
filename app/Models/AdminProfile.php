<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $branch_id
 * @property string|null $profile_photo
 */
class AdminProfile extends Model
{
    protected $table = 'admin_profiles';
    
    protected $fillable = [
        'user_id', 'branch_id', 'employee_number', 'first_name', 'last_name', 'middle_name',
        'position', 'department', 'admin_level', 'can_verify_ids', 'can_create_employees', 'can_manage_accounts',
        'authority_granted_by', 'authority_granted_at', 'permissions', 'date_hired', 'basic_salary',
        'contact_number', 'address', 'profile_photo', 'fingerprint_template', 'signature_path', 'is_fingerprint_registered',
        'employee_profile_id'
    ];
    
    protected $casts = [
        'permissions' => 'array',
        'date_hired' => 'date',
        'basic_salary' => 'decimal:2',
        'is_fingerprint_registered' => 'boolean',
        'authority_granted_at' => 'datetime',
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
        return $this->first_name . ' ' . $this->last_name;
    }

    public function hasRegisteredFingerprint(): bool
    {
        $template = trim((string) ($this->fingerprint_template ?? ''));

        return (bool) $this->is_fingerprint_registered || ($template !== '' && strtolower($template) !== 'null');
    }
}
