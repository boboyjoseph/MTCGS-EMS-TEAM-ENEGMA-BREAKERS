<?php

namespace Tests\Feature;

use App\Models\AdminProfile;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCreateEmployeeAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_admin_type_is_treated_as_super_admin(): void
    {
        $user = new User([
            'role' => 'admin',
            'admin_type' => 'hr',
        ]);

        $this->assertTrue($user->isSuperAdmin());
    }

    public function test_admin_with_null_admin_type_can_access_create_employee_page(): void
    {
        $branch = Branch::create([
            'branch_code' => 'BR-100',
            'branch_name' => 'Main Branch',
            'address' => 'Test Address',
        ]);

        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'admin_type' => null,
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);

        AdminProfile::create([
            'user_id' => $admin->id,
            'branch_id' => $branch->id,
            'employee_number' => 'ADM-001',
            'first_name' => 'System',
            'last_name' => 'Admin',
            'position' => 'System Administrator',
            'department' => 'IT',
            'admin_level' => 'super_admin',
            'can_verify_ids' => true,
            'can_create_employees' => true,
            'can_manage_accounts' => true,
            'date_hired' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.employee-create'));

        $response->assertOk();
        $response->assertSee('name="position"', false);
        $response->assertSee('Employee ID', false);
        $response->assertSee('<select name="role" class="form-select" required id="role-select">', false);
        $response->assertSee('<option value="employee"', false);
        $response->assertSee('<option value="finance_officer"', false);
        $response->assertSee('<option value="finance_head"', false);
    }

    public function test_super_admin_can_change_admin_role_from_edit_employee_page(): void
    {
        $branch = Branch::create([
            'branch_code' => 'BR-ROLE-01',
            'branch_name' => 'Role Dropdown Branch',
            'address' => 'Role Address',
        ]);
        $superAdmin = User::create([
            'name' => 'System Admin',
            'email' => 'role-superadmin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'admin_type' => 'super_admin',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);
        $branchAdmin = User::create([
            'name' => 'Branch Admin',
            'email' => 'role-branchadmin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'admin_type' => 'branch_admin',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);
        $branchAdminProfile = AdminProfile::create([
            'user_id' => $branchAdmin->id,
            'branch_id' => $branch->id,
            'employee_number' => 'ADM-ROLE-01',
            'first_name' => 'Branch',
            'last_name' => 'Admin',
            'position' => 'Branch Administrator',
            'admin_level' => 'branch_admin',
            'date_hired' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.admin-edit', $branchAdminProfile->id))
            ->assertOk()
            ->assertSee('<select id="role" name="admin_type"', false)
            ->assertSee('<option value="branch_admin"', false)
            ->assertSee('<option value="hr"', false)
            ->assertSee('<option value="super_admin"', false);

        $this->put(route('admin.admin-update', $branchAdminProfile->id), [
            'first_name' => 'Branch',
            'last_name' => 'Admin',
            'position' => 'Branch Administrator',
            'branch_id' => $branch->id,
            'date_hired' => now()->format('Y-m-d'),
            'admin_type' => 'hr',
        ])->assertRedirect(route('admin.employees', absolute: false));

        $this->assertDatabaseHas('users', [
            'id' => $branchAdmin->id,
            'admin_type' => 'hr',
        ]);
        $this->assertDatabaseHas('admin_profiles', [
            'id' => $branchAdminProfile->id,
            'admin_level' => 'hr',
        ]);
    }

    public function test_branch_admin_cannot_escalate_admin_role(): void
    {
        $branch = Branch::create([
            'branch_code' => 'BR-ROLE-02',
            'branch_name' => 'Protected Role Branch',
            'address' => 'Role Address',
        ]);
        $branchAdmin = User::create([
            'name' => 'Branch Admin',
            'email' => 'role-protected-admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'admin_type' => 'branch_admin',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);
        $targetUser = User::create([
            'name' => 'Other Branch Admin',
            'email' => 'role-target-admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'admin_type' => 'branch_admin',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);
        $targetProfile = AdminProfile::create([
            'user_id' => $targetUser->id,
            'branch_id' => $branch->id,
            'employee_number' => 'ADM-ROLE-02',
            'first_name' => 'Other',
            'last_name' => 'Admin',
            'position' => 'Branch Administrator',
            'admin_level' => 'branch_admin',
            'date_hired' => now(),
        ]);

        $this->actingAs($branchAdmin)
            ->put(route('admin.admin-update', $targetProfile->id), [
                'first_name' => 'Other',
                'last_name' => 'Admin',
                'position' => 'Branch Administrator',
                'branch_id' => $branch->id,
                'date_hired' => now()->format('Y-m-d'),
                'admin_type' => 'super_admin',
            ])
            ->assertForbidden();

        $this->assertSame('branch_admin', $targetUser->fresh()->admin_type);
    }
}
