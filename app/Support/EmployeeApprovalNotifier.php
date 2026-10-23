<?php

namespace App\Support;

use App\Models\AdminProfile;
use App\Models\User;
use App\Notifications\SystemNotification;

class EmployeeApprovalNotifier
{
    public function notify(User $employee): void
    {
        User::query()
            ->where('role', 'admin')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereIn('admin_type', ['super_admin', 'hr'])
                    ->orWhereNull('admin_type')
                    ->orWhereHasMorph('profile', [AdminProfile::class], function ($profileQuery) {
                        $profileQuery->where('can_verify_ids', true);
                    });
            })
            ->get()
            ->each(fn (User $approver) => $approver->notify(new SystemNotification(
                'Employee account pending approval',
                'A new employee account for '.$employee->name.' is waiting for HR approval.',
                'employee_account_pending_approval',
                route('admin.verifications')
            )));
    }
}
