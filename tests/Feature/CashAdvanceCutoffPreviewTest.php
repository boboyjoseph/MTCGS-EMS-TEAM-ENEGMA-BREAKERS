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

    public function test_preview_does_not_show_a_cutoff_without_a_draft_payroll_period(): void
    {
        $branch = Branch::create([
            'branch_code' => 'MTC-NO-DRAFT',
            'branch_name' => 'No Draft Branch',
            'address' => 'No Draft Address',
        ]);
        $superAdmin = User::factory()->create([
            'role' => 'admin',
            'admin_type' => 'super_admin',
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('cash-advances.index'))
            ->assertOk()
            ->assertSee('No draft payroll periods are available for your branch scope.')
            ->assertDontSee('Payroll Preview: Sep');

        $this->actingAs($superAdmin)
            ->get(route('admin.payroll.periods'))
            ->assertOk()
            ->assertSee('No draft payroll periods are available for your branch scope.')
            ->assertDontSee('Payroll Preview: Sep');
    }

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
        $draftPreviewPeriod = PayrollPeriod::create([
            'branch_id' => $branch->id,
            'period_code' => 'PRD-CA-DRAFT-2026-09',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-15',
            'payment_date' => '2026-09-18',
            'status' => 'draft',
        ]);
        $previousPayrollPeriod = PayrollPeriod::create([
            'branch_id' => $branch->id,
            'period_code' => 'PRD-CA-HISTORY-2026-08',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-15',
            'payment_date' => '2026-08-30',
            'status' => 'completed',
            'approved_at' => '2026-08-16 10:00:00',
            'hr_approved_at' => '2026-08-17 10:00:00',
            'branch_approved_at' => '2026-08-18 10:00:00',
            'admin_approval_stage' => 'bh_approved',
        ]);
        $cutoffPayrollPeriod = PayrollPeriod::create([
            'branch_id' => $branch->id,
            'period_code' => 'PRD-CA-PREVIEW-2026-09',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-15',
            'payment_date' => '2026-09-18',
            'status' => 'completed',
            'approved_at' => '2026-09-16 10:00:00',
            'hr_approved_at' => '2026-09-17 10:00:00',
            'branch_approved_at' => '2026-09-18 10:00:00',
            'admin_approval_stage' => 'bh_approved',
        ]);
        $secondBranch = Branch::create([
            'branch_code' => 'MTC-TEST-2',
            'branch_name' => 'Second Test Branch',
            'address' => 'Second Test Address',
        ]);
        $secondEmployeeUser = User::factory()->create(['branch_id' => $secondBranch->id]);
        $secondProfile = EmployeeProfile::create([
            'user_id' => $secondEmployeeUser->id,
            'branch_id' => $secondBranch->id,
            'employee_number' => 'EMP-TEST-002',
            'first_name' => 'Second',
            'last_name' => 'Employee',
            'position' => 'Staff',
            'date_hired' => '2020-01-01',
            'status' => 'Regular',
        ]);
        $secondBranchPeriod = PayrollPeriod::create([
            'branch_id' => $secondBranch->id,
            'period_code' => 'PRD-CA-DRAFT-2026-10',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-15',
            'payment_date' => '2026-10-18',
            'status' => 'draft',
        ]);
        CashAdvanceApplication::create([
            'employee_profile_id' => $secondProfile->id,
            'requested_amount' => 700,
            'approved_amount' => 700,
            'installment_amount' => 350,
            'installments' => 2,
            'deducted_installments' => 0,
            'purpose' => 'Second branch cutoff preview test',
            'eligibility_category' => 'regular',
            'status' => 'approved',
            'fh_reviewed_at' => '2026-10-14 10:00:00',
            'created_at' => '2026-10-14 09:00:00',
            'updated_at' => '2026-10-14 10:00:00',
        ]);
        PayrollEntry::create([
            'payroll_period_id' => $cutoffPayrollPeriod->id,
            'employee_profile_id' => $profile->id,
            'branch_id' => $branch->id,
            'basic_pay' => 20000,
            'gross_pay' => 20000,
            'cash_advance_deduction' => 250,
            'total_deductions' => 250,
            'net_pay' => 19750,
            'status' => 'approved',
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
            ->assertSee('Payroll Preview: Test Branch | Sep 1–Sep 15, 2026')
            ->assertSee('Payroll Preview: Second Test Branch | Oct 1–Oct 15, 2026')
            ->assertSee('View Full History')
            ->assertSee('2 eligible approved cash advance applications')
            ->assertSee('400.00')
            ->assertSee('900.00')
            ->assertSee('1,300.00')
            ->assertSee('Not posted')
            ->assertDontSee('Approved Cash Advance History')
            ->assertDontSee('Cash Advances Already Posted to This Payroll')
            ->assertDontSee('PRD-CA-PREVIEW-2026-09')
            ->assertDontSee('Installment')
            ->assertDontSee('200.00')
            ->assertDontSee('450.00')
            ->assertDontSee('500.00');

        $this->actingAs($superAdmin)
            ->get(route('admin.payroll.periods'))
            ->assertOk()
            ->assertSee('Payroll Preview: Test Branch | Sep 1–Sep 15, 2026')
            ->assertSee('Payroll Preview: Second Test Branch | Oct 1–Oct 15, 2026')
            ->assertSee('View Full History')
            ->assertDontSee('Approved Cash Advance History')
            ->assertSee('2 eligible approved cash advance applications')
            ->assertSee('400.00')
            ->assertSee('900.00')
            ->assertSee('1,300.00')
            ->assertSee('Not posted')
            ->assertDontSee('Installment')
            ->assertDontSee('200.00');

        $branchAdmin = User::factory()->create([
            'role' => 'admin',
            'admin_type' => 'branch_admin',
            'branch_id' => $branch->id,
        ]);
        $this->actingAs($branchAdmin)
            ->get(route('cash-advances.index'))
            ->assertOk()
            ->assertSee('Payroll Preview: Test Branch | Sep 1–Sep 15, 2026')
            ->assertDontSee('Payroll Preview: Second Test Branch | Oct 1–Oct 15, 2026');
        $this->actingAs($branchAdmin)
            ->get(route('admin.payroll.periods'))
            ->assertOk()
            ->assertSee('Payroll Preview: Test Branch | Sep 1–Sep 15, 2026')
            ->assertDontSee('Payroll Preview: Second Test Branch | Oct 1–Oct 15, 2026');

        $this->actingAs($superAdmin)
            ->get(route('cash-advances.history'))
            ->assertOk()
            ->assertSee('Cash Advance History')
            ->assertSee('PRD-CA-HISTORY-2026-08')
            ->assertSee('400.00')
            ->assertSee('Aug 30, 2026')
            ->assertSee('EMP-TEST-001')
            ->assertSee('Test Employee')
            ->assertSee('Staff')
            ->assertSee('PRD-CA-HISTORY-2026-08')
            ->assertSee('Period Total')
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
        $this->assertDatabaseHas('payroll_periods', [
            'id' => $draftPreviewPeriod->id,
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('payroll_periods', [
            'id' => $secondBranchPeriod->id,
            'status' => 'draft',
        ]);
    }

    public function test_history_shows_cash_advance_once_payroll_approval_is_complete_even_before_period_end(): void
    {
        $branch = Branch::create([
            'branch_code' => 'MTC-HISTORY',
            'branch_name' => 'History Branch',
            'address' => 'History Address',
        ]);
        $employeeUser = User::factory()->create(['branch_id' => $branch->id]);
        $profile = EmployeeProfile::create([
            'user_id' => $employeeUser->id,
            'branch_id' => $branch->id,
            'employee_number' => 'EMP-HISTORY-001',
            'first_name' => 'History',
            'last_name' => 'Employee',
            'position' => 'Teacher',
            'date_hired' => '2020-01-01',
            'status' => 'Regular',
        ]);

        $approvedPayroll = PayrollPeriod::create([
            'branch_id' => $branch->id,
            'period_code' => 'PRD-CA-HISTORY-FUTURE',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'payment_date' => '2026-11-05',
            'status' => 'completed',
            'approved_at' => '2026-10-10 09:00:00',
            'hr_approved_at' => '2026-10-11 09:00:00',
            'branch_approved_at' => '2026-10-12 09:00:00',
            'admin_approval_stage' => 'bh_approved',
        ]);
        PayrollEntry::create([
            'payroll_period_id' => $approvedPayroll->id,
            'employee_profile_id' => $profile->id,
            'branch_id' => $branch->id,
            'basic_pay' => 20000,
            'gross_pay' => 20000,
            'cash_advance_deduction' => 300,
            'total_deductions' => 300,
            'net_pay' => 19700,
            'status' => 'approved',
        ]);

        $notFullyApprovedPayroll = PayrollPeriod::create([
            'branch_id' => $branch->id,
            'period_code' => 'PRD-CA-HISTORY-PENDING',
            'start_date' => '2026-09-16',
            'end_date' => '2026-09-30',
            'payment_date' => '2026-10-05',
            'status' => 'completed',
            'approved_at' => '2026-10-01 09:00:00',
            'hr_approved_at' => '2026-10-02 09:00:00',
        ]);
        PayrollEntry::create([
            'payroll_period_id' => $notFullyApprovedPayroll->id,
            'employee_profile_id' => $profile->id,
            'branch_id' => $branch->id,
            'basic_pay' => 20000,
            'gross_pay' => 20000,
            'cash_advance_deduction' => 500,
            'total_deductions' => 500,
            'net_pay' => 19500,
            'status' => 'approved',
        ]);

        $superAdmin = User::factory()->create([
            'role' => 'admin',
            'admin_type' => 'super_admin',
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('cash-advances.history'))
            ->assertOk()
            ->assertSee('PRD-CA-HISTORY-FUTURE')
            ->assertSee('EMP-HISTORY-001')
            ->assertSee('History Employee')
            ->assertSee('Teacher')
            ->assertSee('Nov 05, 2026')
            ->assertSee('300.00')
            ->assertDontSee('PRD-CA-HISTORY-PENDING');

        $this->actingAs($superAdmin)
            ->get(route('cash-advances.index'))
            ->assertOk()
            ->assertSee('View Full History')
            ->assertDontSee('EMP-HISTORY-001')
            ->assertDontSee('PRD-CA-HISTORY-FUTURE')
            ->assertDontSee('PRD-CA-HISTORY-PENDING')
            ->assertDontSee('500.00');
    }
}