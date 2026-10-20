<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashAdvanceApplication extends Model
{
    protected $fillable = [
        'employee_profile_id', 'requested_amount', 'approved_amount', 'installment_amount',
        'installments', 'purpose', 'eligibility_category', 'status', 'rejection_reason',
        'deducted_installments', 'deduction_started_at', 'completed_at',
        'fo_reviewed_by', 'fo_reviewed_at', 'bh_reviewed_by', 'bh_reviewed_at',
        'hr_reviewed_by', 'hr_reviewed_at', 'fh_reviewed_by', 'fh_reviewed_at',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'fo_reviewed_at' => 'datetime',
        'bh_reviewed_at' => 'datetime',
        'hr_reviewed_at' => 'datetime',
        'fh_reviewed_at' => 'datetime',
        'deduction_started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** @return BelongsTo<EmployeeProfile, $this> */
    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function foReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fo_reviewed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function bhReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bh_reviewed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function hrReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_reviewed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function fhReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fh_reviewed_by');
    }

    public function scopeEligibleForPayrollCutoff($query, ?int $employeeProfileId, $periodStart, $periodEnd)
    {
        return $query->when($employeeProfileId !== null, fn ($query) => $query->where('employee_profile_id', $employeeProfileId))
            ->whereIn('status', ['approved', 'deducting'])
            ->whereColumn('deducted_installments', '<', 'installments')
            ->where(function ($query) use ($periodEnd) {
                $query->whereDate('fh_reviewed_at', '<=', $periodEnd)
                    ->orWhere(function ($unreviewed) use ($periodEnd) {
                        $unreviewed->whereNull('fh_reviewed_at')
                            ->whereDate('created_at', '<=', $periodEnd);
                    });
            });
    }
}
