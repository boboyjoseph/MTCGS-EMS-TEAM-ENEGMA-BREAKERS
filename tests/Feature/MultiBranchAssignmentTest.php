<?php

namespace Tests\Feature;

use App\Models\AdminProfile;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class MultiBranchAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_assign_assisting_branches_to_both_branch_head_account_types(): void
    {
        [$iriga, $buhi] = $this->createBranches();
        $superAdmin = $this->createUser('admin', 'super_admin', $iriga);
        $branchAdmin = $this->createUser('admin', 'branch_admin', $iriga);
        $branchHead = $this->createUser('branch_head', null, $buhi);

        $this->actingAs($superAdmin);
        $this->assertSame('admin', Auth::user()->role);
        $this->assertSame('super_admin', Auth::user()->admin_type);

        $response = $this
            ->put(route('admin.branch-heads.assignments.update', $branchAdmin), [
                'branch_ids' => [$buhi->id],
            ]);
        $response->assertRedirect();

        $this->actingAs($superAdmin)
            ->put(route('admin.branch-heads.assignments.update', $branchHead), [
                'branch_ids' => [$iriga->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_branch_assignments', [
            'user_id' => $branchAdmin->id,
            'branch_id' => $buhi->id,
            'assigned_by' => $superAdmin->id,
        ]);
        $this->assertDatabaseHas('user_branch_assignments', [
            'user_id' => $branchHead->id,
            'branch_id' => $iriga->id,
            'assigned_by' => $superAdmin->id,
        ]);
    }

    public function test_branch_head_can_switch_to_a_super_admin_assigned_branch(): void
    {
        [$iriga, $buhi] = $this->createBranches();
        $branchHead = $this->createUser('branch_head', null, $iriga);
        $branchHead->assignedBranches()->attach($buhi->id);

        $this->actingAs($branchHead)
            ->post(route('active-branch.update'), ['branch_id' => $buhi->id])
            ->assertRedirect()
            ->assertSessionHas('active_branch_id', $buhi->id);

        $this->assertSame($buhi->id, $branchHead->fresh()->getEffectiveBranchId());
    }

    public function test_branch_head_cannot_switch_to_an_unassigned_branch(): void
    {
        [$iriga, $buhi] = $this->createBranches();
        $branchHead = $this->createUser('branch_head', null, $iriga);

        $this->actingAs($branchHead)
            ->post(route('active-branch.update'), ['branch_id' => $buhi->id])
            ->assertForbidden();
    }

    public function test_branch_admin_can_create_employees_in_an_assigned_assisting_branch(): void
    {
        [$iriga, $buhi] = $this->createBranches();
        $superAdmin = $this->createUser('admin', 'super_admin', $iriga);
        $branchAdmin = $this->createUser('admin', 'branch_admin', $iriga);
        $adminProfile = AdminProfile::create([
            'user_id' => $branchAdmin->id,
            'branch_id' => $iriga->id,
            'employee_number' => 'IRG-BH-001',
            'first_name' => 'Iriga',
            'last_name' => 'Branch Head',
            'position' => 'Branch Administrator',
            'department' => 'Operations',
            'admin_level' => 'branch_admin',
        ]);
        $branchAdmin->update([
            'profile_id' => $adminProfile->id,
            'profile_type' => AdminProfile::class,
        ]);
        $branchAdmin->assignedBranches()->attach($buhi->id, ['assigned_by' => $superAdmin->id]);

        $this->actingAs($branchAdmin)
            ->post(route('active-branch.update'), ['branch_id' => $buhi->id])
            ->assertRedirect();

        $this->actingAs($branchAdmin)
            ->get(route('admin.employee-create'))
            ->assertOk()
            ->assertSee('name="branch_id" value="'.$buhi->id.'"', false)
            ->assertSee($buhi->branch_name);

        $this->actingAs($branchAdmin)
            ->post(route('admin.employee-store'), [
                'first_name' => 'Buhi',
                'last_name' => 'Employee',
                'email' => 'buhi.employee@example.com',
                'employee_number' => 'BUH-EMP-001',
                'branch_id' => $buhi->id,
                'position' => 'Teacher',
                'date_hired' => now()->toDateString(),
                'status' => 'New Hire',
                'role' => 'employee',
            ])
            ->assertRedirect(route('admin.employees', absolute: false));

        $this->assertDatabaseHas('employee_profiles', [
            'employee_number' => 'BUH-EMP-001',
            'branch_id' => $buhi->id,
        ]);
    }

    public function test_assignment_page_lists_both_branch_head_account_types(): void
    {
        [$iriga] = $this->createBranches();
        $superAdmin = $this->createUser('admin', 'super_admin', $iriga);
        $this->createUser('admin', 'branch_admin', $iriga);
        $this->createUser('branch_head', null, $iriga);

        $this->actingAs($superAdmin)
            ->get(route('admin.branch-heads.assignments'))
            ->assertOk()
            ->assertSee('Multi-Branch Assignments')
            ->assertSee('Branch Admin')
            ->assertSee('Branch Head');
    }

    public function test_assignment_success_flash_is_rendered_only_once(): void
    {
        [$iriga] = $this->createBranches();
        $superAdmin = $this->createUser('admin', 'super_admin', $iriga);
        $message = 'Branch assistance assignments updated for December Ten.';

        $response = $this->actingAs($superAdmin)
            ->withSession(['success' => $message])
            ->get(route('admin.branch-heads.assignments'))
            ->assertOk()
            ->assertSee($message)
            ->assertSee('data-auto-dismiss="5000"', false)
            ->assertSee('scheduleSuccessAlertDismissal', false);

        $this->assertSame(1, substr_count($response->getContent(), $message));
    }

    /**
     * @return array{Branch, Branch}
     */
    private function createBranches(): array
    {
        $iriga = Branch::create([
            'branch_code' => 'IRG',
            'branch_name' => 'Iriga',
            'address' => 'Iriga',
        ]);
        $buhi = Branch::create([
            'branch_code' => 'BUH',
            'branch_name' => 'Buhi',
            'address' => 'Buhi',
        ]);

        return [$iriga, $buhi];
    }

    private function createUser(string $role, ?string $adminType, Branch $branch): User
    {
        return User::create([
            'name' => ucfirst($role).' Test',
            'email' => $role.'-'.$adminType.'-'.$branch->id.'@example.com',
            'password' => bcrypt('password'),
            'role' => $role,
            'admin_type' => $adminType,
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);
    }
}
