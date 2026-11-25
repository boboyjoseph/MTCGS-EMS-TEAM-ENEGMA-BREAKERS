<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use CanResetPassword, HasApiTokens, HasFactory, MustVerifyEmail, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'admin_type', 'profile_id', 'profile_type', 'branch_id',
        'id_verification_status', 'id_document_path', 'id_document_type',
        'rejection_reason', 'is_verified', 'is_active', 'email_verified_at', 'last_login_at', 'pending_password'
    ];

    protected $hidden = ['password', 'remember_token'];
    
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
    ];

    /** @return MorphTo<\Illuminate\Database\Eloquent\Model, $this> */
    public function profile(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsToMany<Branch, $this> */
    public function assignedBranches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'user_branch_assignments')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    /** @return HasMany<AuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /** @return HasMany<LoginHistory, $this> */
    public function loginHistories(): HasMany
    {
        return $this->hasMany(LoginHistory::class);
    }
    
    // Helper methods para makuha ang profile ng user ayon sa role
    public function getEmployeeProfile(): ?EmployeeProfile
    {
        if ($this->role === 'employee' && $this->profile_type === EmployeeProfile::class) {
            $profile = $this->profile;

            return $profile instanceof EmployeeProfile ? $profile : null;
        }
        return null;
    }

    public function canApplyForCashAdvance(): bool
    {
        if ($this->role === 'admin' && $this->admin_type === 'branch_admin') {
            return true;
        }

        $profile = match ($this->role) {
            'employee' => $this->getEmployeeProfile(),
            'finance_officer' => $this->getFinanceProfile()?->employeeProfile,
            default => null,
        };
        $status = strtolower(trim((string) ($profile?->status ?? '')));

        if ($status === '' && $this->role === 'finance_officer') {
            $status = strtolower(trim((string) ($this->getFinanceProfile()?->status ?? '')));
        }

        return in_array($status, [
            'regular',
            '1 year of service',
            '1-2 years in service',
            '3+ years of service',
            '3+ year of service',
        ], true);
    }
    
    public function getFinanceProfile(): ?FinanceProfile
    {
        if (in_array($this->role, ['finance_officer', 'finance_head'], true) && $this->profile_type === FinanceProfile::class) {
            $profile = $this->profile;

            return $profile instanceof FinanceProfile ? $profile : null;
        }
        return null;
    }
    
    public function getAdminProfile(): ?AdminProfile
    {
        if ($this->role !== 'admin') {
            return null;
        }

        $profile = $this->profile;
        if ($this->profile_type === AdminProfile::class && $profile instanceof AdminProfile) {
            return $profile;
        }

        return AdminProfile::where('user_id', $this->id)->orderBy('id')->first();
    }
    
    public function getBranchHeadProfile(): ?BranchHeadProfile
    {
        if ($this->role === 'branch_head' && $this->profile_type === BranchHeadProfile::class) {
            $profile = $this->profile;

            return $profile instanceof BranchHeadProfile ? $profile : null;
        }
        return null;
    }
    
    public function getPrimaryBranchId(): ?int
    {
        if (!is_null($this->branch_id)) {
            return (int) $this->branch_id;
        }

        $profile = $this->profile;
        if ($profile instanceof EmployeeProfile
            || $profile instanceof Employee
            || $profile instanceof FinanceProfile
            || $profile instanceof AdminProfile
            || $profile instanceof BranchHeadProfile) {
            return $profile->branch_id === null ? null : (int) $profile->branch_id;
        }

        return null;
    }

    /**
     * @return array<int>
     */
    public function getAccessibleBranchIds(): array
    {
        $branchIds = $this->assignedBranches()->pluck('branches.id')
            ->map(fn ($branchId) => (int) $branchId)
            ->all();
        $primaryBranchId = $this->getPrimaryBranchId();

        if ($primaryBranchId !== null) {
            $branchIds[] = $primaryBranchId;
        }

        return array_values(array_unique($branchIds));
    }

    public function canAccessBranch(int $branchId): bool
    {
        return in_array($branchId, $this->getAccessibleBranchIds(), true);
    }

    public function getEffectiveBranchId(): ?int
    {
        $primaryBranchId = $this->getPrimaryBranchId();
        $activeBranchId = session('active_branch_id');

        if ($this->isBranchAdmin()
            && is_numeric($activeBranchId)
            && $this->canAccessBranch((int) $activeBranchId)) {
            return (int) $activeBranchId;
        }

        return $primaryBranchId;
    }

    public function getAdminBranch(): ?Branch
    {
        $branchId = $this->getEffectiveBranchId();

        return $branchId === null ? null : Branch::find($branchId);
    }

    public function isSuperAdmin()
    {
        return $this->role === 'admin'
            && (in_array($this->admin_type, ['super_admin', 'hr'], true) || empty($this->admin_type));
    }
    
    public function isBranchAdmin()
    {
        return ($this->role === 'admin' && $this->admin_type === 'branch_admin')
            || $this->role === 'branch_head';
    }

    public function isBranchHead()
    {
        return $this->role === 'branch_head';
    }
    
    public function isAdmin()
    {
        return $this->role === 'admin' || $this->role === 'branch_head';
    }
    
    public function isFinanceOfficer()
    {
        return in_array($this->role, ['finance_officer', 'finance_head'], true);
    }

    public function isFinanceHead()
    {
        return $this->role === 'finance_head';
    }

    public function isFinanceStaff()
    {
        return $this->isFinanceOfficer();
    }
    
    public function isEmployee()
    {
        return $this->role === 'employee';
    }
}
