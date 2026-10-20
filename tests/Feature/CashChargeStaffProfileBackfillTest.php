<?php

namespace Tests\Feature;

use App\Models\AdminProfile;
use App\Models\Branch;
use App\Models\EmployeeProfile;
use App\Models\FinanceProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CashChargeStaffProfileBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_unlinked_buhi_admin_and_finance_profiles_are_added_to_cash_charge_selector_without_removing_records(): void
    {
        $branch = Branch::create([
            'branch_code' => 'BR-BUHI-STAFF-BACKFILL',
            'branch_name' => 'Buhi Branch',
            'address' => 'Buhi',
        ]);
        $superAdmin = User::factory()->create([
            'role' => 'admin',
            'admin_type' => 'super_admin',
        ]);
        $branchAdmin = User::factory()->create([
            'role' => 'admin',
            'admin_type' => 'branch_admin',
            'branch_id' => $branch->id,
        ]);
        $financeOfficer = User::factory()->create([
            'role' => 'finance_officer',
            'branch_id' => $branch->id,
        ]);
        $adminProfile = AdminProfile::create([
            'user_id' => $branchAdmin->id,
            'branch_id' => $branch->id,
            'employee_number' => 'ADM-BUHI-BACKFILL',
            'first_name' => 'Buhi',
            'last_name' => 'Administrator',
            'position' => 'Branch Administrator',
            'admin_level' => 'branch_admin',
            'date_hired' => '2024-01-15',
        ]);
        $financeProfile = FinanceProfile::create([
            'user_id' => $financeOfficer->id,
            'branch_id' => $branch->id,
            'employee_number' => 'FIN-BUHI-BACKFILL',
            'first_name' => 'Buhi',
            'last_name' => 'Finance',
            'position' => 'Finance Officer',
            'date_hired' => '2024-01-15',
        ]);
        $unassignedFinanceUser = User::factory()->create([
            'role' => 'finance_officer',
            'branch_id' => null,
        ]);
        $unassignedFinanceProfile = FinanceProfile::create([
            'user_id' => $unassignedFinanceUser->id,
            'branch_id' => null,
            'employee_number' => 'FIN-UNASSIGNED-BACKFILL',
            'first_name' => 'Unassigned',
            'last_name' => 'Finance',
            'position' => 'Finance Officer',
        ]);

        $originalAdminProfileCount = AdminProfile::count();
        $originalFinanceProfileCount = FinanceProfile::count();
        $originalEmployeeProfileCount = EmployeeProfile::count();
        Log::spy();

        $migration = require base_path(
            'database/migrations/2026_10_20_000001_link_active_cash_charge_staff_to_employee_profiles.php'
        );
        $migration->up();
        $migration->up();

        $this->assertSame($originalAdminProfileCount, AdminProfile::count());
        $this->assertSame($originalFinanceProfileCount, FinanceProfile::count());
        $this->assertSame($originalEmployeeProfileCount + 2, EmployeeProfile::count());
        $this->assertNull($unassignedFinanceProfile->fresh()->employee_profile_id);
        Log::shouldHaveReceived('warning')
            ->twice()
            ->with("Skipped finance_profiles record {$unassignedFinanceProfile->id}: missing required branch.");

        $adminProfile->refresh();
        $financeProfile->refresh();
        $this->assertNotNull($adminProfile->employee_profile_id);
        $this->assertNotNull($financeProfile->employee_profile_id);
        $this->assertDatabaseHas('employee_profiles', [
            'id' => $adminProfile->employee_profile_id,
            'user_id' => $branchAdmin->id,
            'branch_id' => $branch->id,
            'employee_number' => 'ADM-BUHI-BACKFILL',
        ]);
        $this->assertDatabaseHas('employee_profiles', [
            'id' => $financeProfile->employee_profile_id,
            'user_id' => $financeOfficer->id,
            'branch_id' => $branch->id,
            'employee_number' => 'FIN-BUHI-BACKFILL',
        ]);

        $response = $this->actingAs($superAdmin)->get(route('admin.cash-charges.index'));
        $response->assertOk()
            ->assertSee('optgroup label="Buhi Branch - Branch Admin"', false)
            ->assertSee('optgroup label="Buhi Branch - Finance Officer"', false)
            ->assertSee('ADM-BUHI-BACKFILL')
            ->assertSee('FIN-BUHI-BACKFILL');

        $this->actingAs($superAdmin)->post(route('admin.cash-charges.store'), [
            'employee_id' => $financeProfile->employee_profile_id,
            'amount' => '250',
            'reason' => 'Buhi finance charge test',
        ])->assertRedirect();

        $this->assertDatabaseHas('cash_charges', [
            'employee_profile_id' => $financeProfile->employee_profile_id,
            'branch_id' => $branch->id,
            'amount' => '250.00',
        ]);
    }
}
