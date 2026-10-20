<?php

namespace App\Services;

use App\Models\CashAdvanceApplication;
use App\Models\EmployeeProfile;
use App\Models\PayrollPeriod;
use Illuminate\Support\Collection;

class CashAdvancePayrollPreviewService
{
    public function forPeriods(Collection $periods, ?EmployeeProfile $profile = null): Collection
    {
        return $periods->map(function (PayrollPeriod $period) use ($profile) {
            $applications = CashAdvanceApplication::with('employeeProfile')
                ->eligibleForPayrollCutoff(
                    $profile?->id,
                    $period->start_date->copy()->startOfDay(),
                    $period->end_date->copy()->endOfDay()
                )
                ->whereHas('employeeProfile', fn ($query) => $query->where('branch_id', $period->branch_id))
                ->orderBy('fh_reviewed_at')
                ->orderBy('created_at')
                ->get();

            return (object) [
                'period' => $period,
                'applications' => $applications,
                'approvedTotal' => $applications->sum(fn ($application) =>
                    (float) ($application->approved_amount ?: $application->requested_amount)
                ),
            ];
        });
    }
}
