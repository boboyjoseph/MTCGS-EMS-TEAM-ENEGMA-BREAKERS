<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->linkMissingProfiles(
                'admin_profiles',
                fn ($query) => $query->where('users.role', 'admin')
                    ->where('users.admin_type', 'branch_admin')
            );
            $this->linkMissingProfiles(
                'finance_profiles',
                fn ($query) => $query->whereIn('users.role', ['finance_officer', 'finance_head'])
            );
        });
    }

    public function down(): void
    {
        // Keep generated employee profiles: they may have acquired attendance or cash-charge records.
    }

    private function linkMissingProfiles(string $profileTable, Closure $roleFilter): void
    {
        $profiles = DB::table($profileTable.' as profiles')
            ->join('users', 'users.id', '=', 'profiles.user_id')
            ->whereNull('profiles.employee_profile_id')
            ->where('users.is_active', true)
            ->where($roleFilter);
        $columns = [
            'profiles.id as profile_id',
            'profiles.user_id',
            'profiles.branch_id as profile_branch_id',
            'profiles.employee_number',
            'profiles.first_name',
            'profiles.last_name',
            'profiles.middle_name',
            'profiles.position',
            'profiles.department',
            'profiles.date_hired',
            'users.branch_id as user_branch_id',
            'users.created_at as user_created_at',
        ];
        foreach (['basic_salary', 'status'] as $optionalColumn) {
            if (Schema::hasColumn($profileTable, $optionalColumn)) {
                $columns[] = 'profiles.'.$optionalColumn;
            } else {
                $columns[] = DB::raw('NULL as '.$optionalColumn);
            }
        }

        $profiles->select($columns)
            ->orderBy('profiles.id')
            ->get()
            ->each(function (object $profile) use ($profileTable): void {
                $branchId = $profile->profile_branch_id ?? $profile->user_branch_id;
                $employeeNumber = trim((string) $profile->employee_number);
                $firstName = trim((string) $profile->first_name);
                $lastName = trim((string) $profile->last_name);

                if (! $branchId || $employeeNumber === '' || $firstName === '' || $lastName === '') {
                    $missingFields = [];
                    if (! $branchId) {
                        $missingFields[] = 'branch';
                    }
                    if ($employeeNumber === '') {
                        $missingFields[] = 'employee_number';
                    }
                    if ($firstName === '') {
                        $missingFields[] = 'first_name';
                    }
                    if ($lastName === '') {
                        $missingFields[] = 'last_name';
                    }

                    $message = "Skipped {$profileTable} record {$profile->profile_id}: missing required ".implode(', ', $missingFields).'.';
                    Log::warning($message);
                    fwrite(STDERR, "WARNING: {$message}\n");

                    return;
                }

                if (! DB::table('branches')->where('id', $branchId)->exists()) {
                    throw new RuntimeException(
                        "Cannot create an employee profile for {$profileTable} record {$profile->profile_id}: its branch does not exist."
                    );
                }

                $employeeProfile = DB::table('employee_profiles')
                    ->where('user_id', $profile->user_id)
                    ->first();

                if (! $employeeProfile) {
                    $employeeProfile = DB::table('employee_profiles')
                        ->where('employee_number', $employeeNumber)
                        ->first();
                }

                if ($employeeProfile) {
                    if ((int) $employeeProfile->user_id !== (int) $profile->user_id
                        || (int) $employeeProfile->branch_id !== (int) $branchId) {
                        throw new RuntimeException(
                            "Cannot link {$profileTable} record {$profile->profile_id}: its employee profile number is already assigned to a different user or branch."
                        );
                    }

                    $employeeProfileId = $employeeProfile->id;
                } else {
                    $employeeProfileId = DB::table('employee_profiles')->insertGetId([
                        'user_id' => $profile->user_id,
                        'branch_id' => $branchId,
                        'employee_number' => $employeeNumber,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'middle_name' => $profile->middle_name,
                        'position' => $profile->position ?: 'Staff',
                        'department' => $profile->department,
                        'date_hired' => $profile->date_hired
                            ?: ($profile->user_created_at ? substr($profile->user_created_at, 0, 10) : now()->toDateString()),
                        'status' => $profile->status ?: 'New Hire',
                        'basic_salary' => $profile->basic_salary ?? 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table($profileTable)
                    ->where('id', $profile->profile_id)
                    ->whereNull('employee_profile_id')
                    ->update(['employee_profile_id' => $employeeProfileId]);
            });
    }
};
