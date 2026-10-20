<?php

namespace Tests\Feature;

use App\Models\AdminProfile;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_an_admin_profile_without_changing_it(): void
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
            'admin_type' => 'super_admin',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);

        $profile = AdminProfile::create([
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

        $response = $this->actingAs($admin)->get(route('admin.user-view', [
            'role' => 'admin',
            'id' => $profile->id,
        ]));

        $response->assertOk();
        $response->assertSee('System Admin');
        $this->assertDatabaseHas('admin_profiles', [
            'id' => $profile->id,
            'user_id' => $admin->id,
            'employee_number' => 'ADM-001',
        ]);
    }

    public function test_non_super_admin_cannot_view_user_management_profile(): void
    {
        $admin = User::create([
            'name' => 'Branch Admin',
            'email' => 'branch-admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'admin_type' => 'branch_admin',
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.user-view', [
            'role' => 'admin',
            'id' => 1,
        ]));

        $response->assertForbidden();
    }
}
