<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\AdminProfile;
use App\Models\Branch;
use App\Models\DTR;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DashboardPendingLeaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_dashboard_counts_system_admin_pending_leaves(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'admin_type' => 'super_admin',
        ]);

        $profile = EmployeeProfile::create([
            'user_id' => $user->id,
            'branch_id' => 1,
            'employee_number' => 'EMP-001',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'position' => 'Staff',
            'employment_type' => 'Regular',
            'status' => 'Regular',
            'date_hired' => now()->toDateString(),
            'is_fingerprint_registered' => false,
        ]);

        LeaveRequest::create([
            'employee_id' => $user->id,
            'employee_profile_id' => $profile->id,
            'leave_type' => 'vacation',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'total_days' => 1,
            'reason' => 'Test leave pending system admin review',
            'status' => 'pending_system_admin',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas('pendingLeaves', 1);
    }

    public function test_employee_dashboard_shows_registered_when_a_template_exists_but_flag_is_stale(): void
    {
        Storage::fake('public');
        $user = User::factory()->create([
            'role' => 'employee',
            'profile_type' => EmployeeProfile::class,
        ]);
        $profile = EmployeeProfile::create([
            'user_id' => $user->id,
            'branch_id' => 1,
            'employee_number' => 'EMP-BIOMETRIC-STATUS',
            'first_name' => 'Fingerprint',
            'last_name' => 'Employee',
            'position' => 'Staff',
            'employment_type' => 'Regular',
            'status' => 'Regular',
            'date_hired' => now()->toDateString(),
            'profile_photo' => 'profile-photos/employeeprofile-avatar-test.jpg',
            'fingerprint_template' => 'saved-fingerprint-template',
            'is_fingerprint_registered' => false,
        ]);
        Storage::disk('public')->put($profile->profile_photo, 'fake-image');
        $user->forceFill([
            'profile_id' => $profile->id,
            'profile_type' => EmployeeProfile::class,
        ])->save();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Registered')
            ->assertDontSee('Not Registered')
            ->assertSee(route('profile.photo', ['employeeprofile', $profile->id]), false)
            ->assertSee('alt="Profile photo"', false)
            ->assertSee('body.dark-mode .employee-dashboard-theme .employee-schedule-title', false)
            ->assertSee('<h5 class="fw-bold employee-schedule-title mb-2">No schedule assigned</h5>', false);

        $photoResponse = $this->get(route('profile.photo', ['employeeprofile', $profile->id]));
        $photoResponse->assertOk();
    }

    public function test_branch_admin_dtr_badge_only_counts_submitted_dtrs_in_their_branch(): void
    {
        $buhi = Branch::create([
            'branch_code' => 'BUHI-DTR-BADGE',
            'branch_name' => 'Buhi Branch',
            'address' => 'Buhi',
        ]);
        $iriga = Branch::create([
            'branch_code' => 'IRIGA-DTR-BADGE',
            'branch_name' => 'Iriga Branch',
            'address' => 'Iriga',
        ]);
        $branchAdmin = User::factory()->create([
            'role' => 'admin',
            'admin_type' => 'branch_admin',
            'branch_id' => $buhi->id,
        ]);
        AdminProfile::create([
            'user_id' => $branchAdmin->id,
            'branch_id' => $buhi->id,
            'employee_number' => 'BUHI-ADMIN-001',
            'first_name' => 'Buhi',
            'last_name' => 'Admin',
            'position' => 'Branch Administrator',
            'date_hired' => now()->toDateString(),
        ]);

        $buhiEmployeeUser = User::factory()->create([
            'role' => 'employee',
            'branch_id' => $buhi->id,
        ]);
        $irigaEmployeeUser = User::factory()->create([
            'role' => 'employee',
            'branch_id' => $iriga->id,
        ]);
        $buhiEmployee = EmployeeProfile::create([
            'user_id' => $buhiEmployeeUser->id,
            'branch_id' => $buhi->id,
            'employee_number' => 'BUHI-DTR-001',
            'first_name' => 'Buhi',
            'last_name' => 'Employee',
            'position' => 'Teacher',
            'status' => 'Regular',
            'date_hired' => now()->toDateString(),
        ]);
        $irigaEmployee = EmployeeProfile::create([
            'user_id' => $irigaEmployeeUser->id,
            'branch_id' => $iriga->id,
            'employee_number' => 'IRIGA-DTR-001',
            'first_name' => 'Iriga',
            'last_name' => 'Employee',
            'position' => 'Teacher',
            'status' => 'Regular',
            'date_hired' => now()->toDateString(),
        ]);

        DTR::create([
            'employee_profile_id' => $buhiEmployee->id,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-15',
            'status' => 'submitted',
        ]);
        DTR::create([
            'employee_profile_id' => $buhiEmployee->id,
            'period_start' => '2026-10-16',
            'period_end' => '2026-10-31',
            'status' => 'draft',
        ]);
        DTR::create([
            'employee_profile_id' => $irigaEmployee->id,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-15',
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($branchAdmin)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('DTR Management');
        $response->assertSee('<span class="badge bg-warning ms-auto">1</span>', false);
        $response->assertDontSee('<span class="badge bg-warning ms-auto">2</span>', false);
    }
}
