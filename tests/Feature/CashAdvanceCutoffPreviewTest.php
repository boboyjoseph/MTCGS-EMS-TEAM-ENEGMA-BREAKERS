<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashAdvanceApplication;
use App\Models\EmployeeProfile;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashAdvanceCutoffPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_cutoff_preview_shows_active_loans_as_of_cutoff_without_posting(): void
    {
        $branch = Branch::create([
            'branch_code' => 'MTC-TEST',
            'branch_name' => 'Test Branch',
            'address' => 'Test Address',
        ]);
        $employeeUser = User::factory()->create(['branch_id' => $branch->id]);
        $profile = EmployeeProfile::create([
            'user_id' => $employeeUser->id,
            'branch_id' => $branch->id,
            'employee_number' => 'EMP-TEST-001',
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'position' => 'Staff',
            'date_hired' => '2020-01-01',
            'status' => 'Regular',
        ]);

        $employeeUser->update([
            'profile_id' => $profile->id,
            'profile_type' => EmployeeProfile::class,
        ]);

        $application = CashAdvanceApplication::create([
            'employee_profile_id' => $profile->id,
            'requested_amount' => 400,
            'approved_amount' => 400,
            'installment_amount' => 200,
            'installments' => 2,
            'deducted_installments' => 0,
            'purpose' => 'Cutoff preview test',
            'eligibility_category' => 'regular',
            'status' => 'approved',
            'fh_reviewed_at' => '2026-09-14 10:00:00',
            'created_at' => '2026-09-14 09:00:00',
            'updated_at' => '2026-09-14 10:00:00',
        ]);

        $outsideCutoff = CashAdvanceApplication::create([
            'employee_profile_id' => $profile->id,
            'requested_amount' => 900,
            'approved_amount' => 900,
            'installment_amount' => 450,
            'installments' => 2,
            'deducted_installments' => 0,
            'purpose' => 'Outside cutoff test',
            'eligibility_category' => 'regular',
            'status' => 'approved',
            'fh_reviewed_at' => '2026-08-20 10:00:00',
            'created_at' => '2026-08-20 09:00:00',
            'updated_at' => '2026-08-20 10:00:00',
        ]);

        $afterCutoff = CashAdvanceApplication::create([
            'employee_profile_id' => $profile->id,
            'requested_amount' => 500,
            'approved_amount' => 500,
            'installment_amount' => 250,
            'installments' => 2,
            'deducted_installments' => 0,
            'purpose' => 'After cutoff test',
            'eligibility_category' => 'regular',
            'status' => 'approved',
            'fh_reviewed_at' => '2026-09-16 10:00:00',
            'created_at' => '2026-09-16 09:00:00',
            'updated_at' => '2026-09-16 10:00:00',
        ]);

        $superAdmin = User::factory()->create([
            'role' => 'admin',
            'admin_type' => 'super_admin',
            'branch_id' => $branch->id,
        ]);
        $previousPayrollPeriod = PayrollPeriod::create([
            'branch_id' => $branch->id,
            'period_code' => 'PRD-CA-HISTORY-2026-08',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-15',
            'payment_date' => '2026-08-30',
            'status' => 'completed',
        ]);
        PayrollEntry::create([
            'payroll_period_id' => $previousPayrollPeriod->id,
            'employee_profile_id' => $profile->id,
            'branch_id' => $branch->id,
            'basic_pay' => 20000,
            'gross_pay' => 20000,
            'cash_advance_deduction' => 400,
            'total_deductions' => 400,
            'net_pay' => 19600,
            'status' => 'calculated',
        ]);

        $response = $this->actingAs($superAdmin)->get(route('cash-advances.index'));

        $response->assertOk()
            ->assertSee(route('cash-advances.history'), false)
            ->assertSee('Payroll Preview: Sep 1–15, 2026')
            ->assertSee('2 eligible approved cash advance applications')
            ->assertSee('400.00')
            ->assertSee('900.00')
            ->assertSee('1,300.00')
            ->assertSee('Not posted')
            ->assertDontSee('Installment')
            ->assertDontSee('200.00')
            ->assertDontSee('450.00')
            ->assertDontSee('500.00');

        $this->actingAs($superAdmin)
            ->get(route('admin.payroll.periods'))
            ->assertOk()
            ->assertSee('Payroll Preview: Sep 1–15, 2026')
            ->assertSee('2 eligible approved cash advance applications')
            ->assertSee('400.00')
            ->assertSee('900.00')
            ->assertSee('1,300.00')
            ->assertSee('Not posted')
            ->assertDontSee('Installment')
            ->assertDontSee('200.00');

        $this->actingAs($superAdmin)
            ->get(route('cash-advances.history'))
            ->assertOk()
            ->assertSee('Cash Advance History')
            ->assertSee('PRD-CA-HISTORY-2026-08')
            ->assertSee('400.00')
            ->assertDontSee('Installment');

        $this->assertDatabaseHas('cash_advance_applications', [
            'id' => $application->id,
            'status' => 'approved',
            'deducted_installments' => 0,
        ]);
        $this->assertDatabaseHas('cash_advance_applications', [
            'id' => $outsideCutoff->id,
            'status' => 'approved',
            'deducted_installments' => 0,
        ]);
        $this->assertDatabaseHas('cash_advance_applications', [
            'id' => $afterCutoff->id,
            'status' => 'approved',
            'deducted_installments' => 0,
        ]);
        $this->assertDatabaseMissing('payroll_periods', [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-15',
        ]);
    }
}