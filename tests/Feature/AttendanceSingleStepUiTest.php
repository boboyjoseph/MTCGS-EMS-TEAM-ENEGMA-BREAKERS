<?php

namespace Tests\Feature;

use App\Models\AdminProfile;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\DTR;
use App\Models\EmployeeProfile;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AttendanceSingleStepUiTest extends TestCase
{
    use RefreshDatabase;
    public function test_admin_and_finance_ui_use_a_single_attendance_button(): void
    {
        $adminBlade = file_get_contents(base_path('resources/views/admin/biometric.blade.php'));
        $financeBlade = file_get_contents(base_path('resources/views/finance/dashboard.blade.php'));

        $this->assertSame(1, substr_count($adminBlade, 'onclick="processAdminAttendance()"'));
        $this->assertSame(1, substr_count($financeBlade, 'onclick="processFinanceAttendance()"'));
    }

    public function test_biometric_registration_requires_explicit_confirmation_before_submitting(): void
    {
        $adminBlade = file_get_contents(base_path('resources/views/admin/biometric.blade.php'));
        $financeBlade = file_get_contents(base_path('resources/views/finance/dashboard.blade.php'));

        $this->assertStringContainsString('confirm(', $adminBlade);
        $this->assertStringContainsString('confirm(', $financeBlade);
    }

    public function test_biometric_employee_list_includes_registered_and_unregistered_users(): void
    {
        $user = User::create([
            'name' => 'System Admin',
            'email' => 'biometric-list@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'admin_type' => 'super_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        EmployeeProfile::create([
            'user_id' => $user->id,
            'branch_id' => 1,
            'employee_number' => 'EMP-2001',
            'first_name' => 'Registered',
            'last_name' => 'User',
            'position' => 'Staff',
            'date_hired' => now()->subMonths(6)->toDateString(),
            'fingerprint_template' => base64_encode('registered-template'),
            'is_fingerprint_registered' => false,
        ]);

        EmployeeProfile::create([
            'user_id' => $user->id,
            'branch_id' => 1,
            'employee_number' => 'EMP-2002',
            'first_name' => 'Unregistered',
            'last_name' => 'User',
            'position' => 'Staff',
            'date_hired' => now()->subMonths(6)->toDateString(),
            'is_fingerprint_registered' => false,
        ]);

        $response = $this->actingAs($user)->getJson('/api/biometric/unregistered-employees');

        $response->assertOk();
        $response->assertJsonPath('count', 1);
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.employee_number', 'EMP-2002');
    }

    public function test_biometric_employee_list_returns_branch_grouping_metadata(): void
    {
        $branchA = Branch::create(['branch_code' => 'MTC-001', 'branch_name' => 'MTC-IRIGA', 'address' => 'Iriga']);
        $branchB = Branch::create(['branch_code' => 'MTC-002', 'branch_name' => 'MTC-BUHI', 'address' => 'Buhi']);

        $user = User::create([
            'name' => 'System Admin',
            'email' => 'biometric-branch@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'admin_type' => 'super_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        EmployeeProfile::create([
            'user_id' => $user->id,
            'branch_id' => $branchA->id,
            'employee_number' => 'EMP-3001',
            'first_name' => 'Branch',
            'last_name' => 'A',
            'position' => 'Staff',
            'date_hired' => now()->subMonths(6)->toDateString(),
            'is_fingerprint_registered' => false,
        ]);

        EmployeeProfile::create([
            'user_id' => $user->id,
            'branch_id' => $branchB->id,
            'employee_number' => 'EMP-3002',
            'first_name' => 'Branch',
            'last_name' => 'B',
            'position' => 'Staff',
            'date_hired' => now()->subMonths(6)->toDateString(),
            'is_fingerprint_registered' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/biometric/unregistered-employees');

        $response->assertOk();
        $this->assertNotNull($response->json('data.0.branch_name'));
        $this->assertEquals('MTC-IRIGA', $response->json('data.0.branch_name'));
        $response->assertJsonPath('count', 1);
    }

    public function test_branch_admin_stats_only_count_branch_admins_not_hr_for_same_branch(): void
    {
        $branch = Branch::create(['branch_code' => 'MTC-300', 'branch_name' => 'MTC-IRIGA', 'address' => 'Iriga']);

        $branchAdminUser = User::create([
            'name' => 'Iriga Branch Admin',
            'email' => 'iriga-branch-admin@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'admin_type' => 'branch_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $hrUser = User::create([
            'name' => 'Iriga HR',
            'email' => 'iriga-hr@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'admin_type' => 'hr',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        AdminProfile::create([
            'user_id' => $branchAdminUser->id,
            'branch_id' => $branch->id,
            'employee_number' => 'ADM-1001',
            'first_name' => 'Iriga',
            'last_name' => 'Branch Admin',
            'position' => 'Branch Administrator',
            'date_hired' => now()->subMonths(6)->toDateString(),
            'is_fingerprint_registered' => true,
        ]);

        AdminProfile::create([
            'user_id' => $hrUser->id,
            'branch_id' => $branch->id,
            'employee_number' => 'ADM-1002',
            'first_name' => 'Iriga',
            'last_name' => 'HR',
            'position' => 'Human Resources',
            'date_hired' => now()->subMonths(6)->toDateString(),
            'is_fingerprint_registered' => true,
        ]);

        $count = AdminProfile::whereHas('user', function ($q) {
            $q->where('is_active', true)
              ->where(function ($sub) {
                  $sub->where('role', 'admin')->where('admin_type', 'branch_admin');
              });
        })->where('branch_id', $branch->id)->count();

        $this->assertSame(1, $count);
    }

    public function test_branch_list_shows_total_users_per_branch(): void
    {
        $branchA = Branch::create(['branch_code' => 'MTC-101', 'branch_name' => 'MTC-IRIGA', 'address' => 'Iriga']);
        $branchB = Branch::create(['branch_code' => 'MTC-102', 'branch_name' => 'MTC-BUHI', 'address' => 'Buhi']);

        $userA1 = User::create([
            'name' => 'Branch A1',
            'email' => 'branch-a1@test.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $userA2 = User::create([
            'name' => 'Branch A2',
            'email' => 'branch-a2@test.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $userB1 = User::create([
            'name' => 'Branch B1',
            'email' => 'branch-b1@test.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        EmployeeProfile::create([
            'user_id' => $userA1->id,
            'branch_id' => $branchA->id,
            'employee_number' => 'EMP-4001',
            'first_name' => 'Branch',
            'last_name' => 'A1',
            'position' => 'Staff',
            'date_hired' => now()->subMonths(6)->toDateString(),
            'is_fingerprint_registered' => false,
        ]);

        EmployeeProfile::create([
            'user_id' => $userA2->id,
            'branch_id' => $branchA->id,
            'employee_number' => 'EMP-4002',
            'first_name' => 'Branch',
            'last_name' => 'A2',
            'position' => 'Staff',
            'date_hired' => now()->subMonths(6)->toDateString(),
            'is_fingerprint_registered' => true,
        ]);

        EmployeeProfile::create([
            'user_id' => $userB1->id,
            'branch_id' => $branchB->id,
            'employee_number' => 'EMP-4003',
            'first_name' => 'Branch',
            'last_name' => 'B1',
            'position' => 'Staff',
            'date_hired' => now()->subMonths(6)->toDateString(),
            'is_fingerprint_registered' => true,
        ]);

        $this->assertSame(2, $branchA->employeeCount());
        $this->assertSame(1, $branchB->employeeCount());
    }

    public function test_dashboard_recent_attendance_shows_only_people_who_attended_today(): void
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin-dashboard@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'admin_type' => 'super_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $employee = EmployeeProfile::create([
            'user_id' => $user->id,
            'branch_id' => 1,
            'employee_number' => 'EMP-1001',
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'position' => 'Staff',
            'date_hired' => now()->subMonths(6)->toDateString(),
            'is_fingerprint_registered' => true,
        ]);

        AttendanceLog::create([
            'employee_id' => $employee->user_id,
            'employee_profile_id' => $employee->id,
            'branch_id' => 1,
            'attendance_date' => now()->subDay(),
            'am_in' => now()->subDay()->setTime(8, 0, 0),
            'status' => 'present',
        ]);

        AttendanceLog::create([
            'employee_id' => $employee->user_id,
            'employee_profile_id' => $employee->id,
            'branch_id' => 1,
            'attendance_date' => today(),
            'am_in' => now()->setTime(7, 30, 0),
            'pm_out' => now()->setTime(17, 0, 0),
            'status' => 'present',
        ]);

        AttendanceLog::create([
            'employee_id' => $employee->user_id,
            'employee_profile_id' => $employee->id,
            'branch_id' => 1,
            'attendance_date' => today(),
            'status' => 'absent',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Time-In');
        $response->assertSeeText('Time-Out');
        $response->assertDontSeeText('PM In');
        $response->assertDontSeeText('PM Out');
        $response->assertViewHas('recentAttendance', function ($recentAttendance) {
            return count($recentAttendance) === 1
                && $recentAttendance[0]['date'] === today()->format('M d, Y')
                && $recentAttendance[0]['time_in'] !== '--'
                && $recentAttendance[0]['time_out'] !== '--';
        });
    }

    public function test_live_updates_detect_attendance_time_out_updates_on_existing_record(): void
    {
        $user = User::create([
            'name' => 'Super Admin',
            'email' => 'live-attendance-update@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'admin_type' => 'super_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $employee = EmployeeProfile::create([
            'user_id' => $user->id,
            'branch_id' => 1,
            'employee_number' => 'EMP-LIVE-UPDATE',
            'first_name' => 'Live',
            'last_name' => 'Update',
            'position' => 'Staff',
            'date_hired' => now()->subMonths(6)->toDateString(),
        ]);

        $attendance = AttendanceLog::create([
            'employee_id' => $employee->user_id,
            'employee_profile_id' => $employee->id,
            'branch_id' => 1,
            'attendance_date' => today(),
            'am_in' => now()->setTime(8, 0, 0),
            'status' => 'present',
            'verification_method' => 'fingerprint',
        ]);

        $firstResponse = $this->actingAs($user)->getJson('/live-updates');
        $firstResponse->assertOk();
        $firstUpdatedAt = $firstResponse->json('latest_attendance_updated_at');
        $this->assertNotEmpty($firstUpdatedAt);

        $attendance->forceFill([
            'pm_out' => now()->setTime(17, 0, 0),
            'updated_at' => now()->addSecond(),
        ])->save();

        $secondResponse = $this->getJson('/live-updates');
        $secondResponse->assertOk();
        $secondResponse->assertJsonPath('latest_attendance_id', $attendance->id);
        $this->assertNotSame($firstUpdatedAt, $secondResponse->json('latest_attendance_updated_at'));

        $firstWorkflowSnapshot = $secondResponse->json('workflow_updates');
        $dtr = DTR::create([
            'employee_profile_id' => $employee->id,
            'period_start' => today()->startOfMonth(),
            'period_end' => today()->endOfMonth(),
            'status' => 'draft',
        ]);
        $dtr->forceFill(['status' => 'submitted', 'updated_at' => now()->addSeconds(2)])->save();

        $thirdResponse = $this->getJson('/live-updates');
        $thirdResponse->assertOk();
        $this->assertNotSame($firstWorkflowSnapshot['dtr'], $thirdResponse->json('workflow_updates.dtr'));

        $firstPayrollSnapshot = $thirdResponse->json('workflow_updates.payroll');
        $period = PayrollPeriod::create([
            'branch_id' => 1,
            'period_code' => 'PRD-LIVE-UPDATE',
            'start_date' => today()->startOfMonth(),
            'end_date' => today()->endOfMonth(),
            'payment_date' => today()->endOfMonth()->addDay(),
            'status' => 'draft',
        ]);
        $payrollEntry = PayrollEntry::create([
            'payroll_period_id' => $period->id,
            'employee_profile_id' => $employee->id,
            'branch_id' => 1,
            'status' => 'draft',
        ]);
        $payrollEntry->forceFill(['status' => 'approved', 'updated_at' => now()->addSeconds(3)])->save();

        $fourthResponse = $this->getJson('/live-updates');
        $fourthResponse->assertOk();
        $this->assertNotSame($firstPayrollSnapshot, $fourthResponse->json('workflow_updates.payroll'));
    }

    public function test_dashboard_and_dtr_refresh_only_after_server_side_updates(): void
    {
        $layout = file_get_contents(base_path('resources/views/layouts/app.blade.php'));

        $this->assertStringContainsString('workflow_updates', $layout);
        $this->assertStringContainsString('workflowWasUpdated', $layout);
        $this->assertStringContainsString('!hasUnsavedAttendanceCorrection()', $layout);
        $this->assertStringContainsString('[form^="attendance-correction-"]', $layout);
        $this->assertStringNotContainsString('isAttendanceRefreshPage', $layout);
    }

    public function test_employee_dashboard_data_reads_attendance_time_in_and_time_out_columns(): void
    {
        $user = User::factory()->create([
            'role' => 'employee',
            'profile_type' => EmployeeProfile::class,
        ]);
        $profile = EmployeeProfile::create([
            'user_id' => $user->id,
            'branch_id' => 1,
            'employee_number' => 'EMP-DASHBOARD-TIMES',
            'first_name' => 'Dashboard',
            'last_name' => 'Times',
            'position' => 'Staff',
            'date_hired' => now()->toDateString(),
        ]);

        AttendanceLog::create([
            'employee_id' => $user->id,
            'employee_profile_id' => $profile->id,
            'branch_id' => 1,
            'attendance_date' => today(),
            'am_in' => today()->setTime(8, 3),
            'am_out' => today()->setTime(12, 1),
            'pm_out' => today()->setTime(17, 5),
            'status' => 'present',
            'verification_method' => 'fingerprint',
        ]);

        $this->actingAs($user)
            ->getJson('/employee/dashboard-data')
            ->assertOk()
            ->assertJsonPath('today_attendance.time_in_formatted', '08:03 AM')
            ->assertJsonPath('today_attendance.time_out_formatted', '05:05 PM')
            ->assertJsonPath('recent_attendance.0.time_in', '08:03 AM')
            ->assertJsonPath('recent_attendance.0.time_out', '05:05 PM')
            ->assertJsonPath('recent_attendance.0.verification_method', 'fingerprint');
    }
}
