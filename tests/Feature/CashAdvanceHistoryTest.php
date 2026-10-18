<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashAdvanceApplication;
use App\Models\EmployeeProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashAdvanceHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_approvers_can_filter_their_cash_advance_history_by_review_period(): void
    {
        $branch = Branch::create([
            'branch_code' => 'CA-001',
            'branch_name' => 'Cash Advance Branch',
            'address' => 'Test Address',
        ]);

        $applicant = User::create([
            'name' => 'Applicant',
            'email' => 'cash-applicant@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
            'profile_type' => EmployeeProfile::class,
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);

        $profile = EmployeeProfile::create([
            'user_id' => $applicant->id,
            'branch_id' => $branch->id,
            'employee_number' => 'CA-EMP-001',
            'first_name' => 'Cash',
            'last_name' => 'Applicant',
            'position' => 'Teacher',
            'date_hired' => '2020-01-01',
            'status' => 'regular',
        ]);

        $reviewers = [
            [
                'user' => User::create([
                    'name' => 'Finance Officer',
                    'email' => 'cash-fo@example.com',
                    'password' => bcrypt('password'),
                    'role' => 'finance_officer',
                    'branch_id' => $branch->id,
                    'is_active' => true,
                    'is_verified' => true,
                    'id_verification_status' => 'approved',
                ]),
                'reviewed_by' => 'fo_reviewed_by',
                'reviewed_at' => 'fo_reviewed_at',
                'status' => 'pending_bh',
            ],
            [
                'user' => User::create([
                    'name' => 'Branch Head',
                    'email' => 'cash-bh@example.com',
                    'password' => bcrypt('password'),
                    'role' => 'admin',
                    'admin_type' => 'branch_admin',
                    'branch_id' => $branch->id,
                    'is_active' => true,
                    'is_verified' => true,
                    'id_verification_status' => 'approved',
                ]),
                'reviewed_by' => 'bh_reviewed_by',
                'reviewed_at' => 'bh_reviewed_at',
                'status' => 'pending_hr',
            ],
            [
                'user' => User::create([
                    'name' => 'HR',
                    'email' => 'cash-hr@example.com',
                    'password' => bcrypt('password'),
                    'role' => 'admin',
                    'admin_type' => 'super_admin',
                    'branch_id' => $branch->id,
                    'is_active' => true,
                    'is_verified' => true,
                    'id_verification_status' => 'approved',
                ]),
                'reviewed_by' => 'hr_reviewed_by',
                'reviewed_at' => 'hr_reviewed_at',
                'status' => 'pending_fh',
            ],
            [
                'user' => User::create([
                    'name' => 'Finance Head',
                    'email' => 'cash-fh@example.com',
                    'password' => bcrypt('password'),
                    'role' => 'finance_head',
                    'branch_id' => $branch->id,
                    'is_active' => true,
                    'is_verified' => true,
                    'id_verification_status' => 'approved',
                ]),
                'reviewed_by' => 'fh_reviewed_by',
                'reviewed_at' => 'fh_reviewed_at',
                'status' => 'approved',
            ],
        ];

        foreach ($reviewers as $index => $reviewer) {
            CashAdvanceApplication::create([
                'employee_profile_id' => $profile->id,
                'requested_amount' => 100 + $index,
                'installments' => 2,
                'purpose' => 'Reviewed by role '.$index,
                'eligibility_category' => 'regular',
                'status' => $reviewer['status'],
                $reviewer['reviewed_by'] => $reviewer['user']->id,
                $reviewer['reviewed_at'] => Carbon::parse('2026-06-15 10:00:00'),
            ]);
        }

        CashAdvanceApplication::create([
            'employee_profile_id' => $profile->id,
            'requested_amount' => 200,
            'installments' => 2,
            'purpose' => 'Outside selected period',
            'eligibility_category' => 'regular',
            'status' => 'pending_bh',
            'fo_reviewed_by' => $reviewers[0]['user']->id,
            'fo_reviewed_at' => Carbon::parse('2026-05-15 10:00:00'),
        ]);

        foreach ($reviewers as $index => $reviewer) {
            $this->actingAs($reviewer['user'])
                ->get(route('cash-advances.index'))
                ->assertOk()
                ->assertSeeText('Cash Advance History');

            $response = $this->actingAs($reviewer['user'])->get(route('cash-advances.index', [
                'view' => 'history',
                'period_start' => '2026-06-01',
                'period_end' => '2026-06-30',
            ]));

            $response->assertOk()
                ->assertSeeText('Reviewed by role '.$index)
                ->assertDontSeeText('Outside selected period');
        }

        $response = $this->actingAs($reviewers[0]['user'])->get(route('cash-advances.index', [
            'view' => 'history',
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
        ]));

        $response->assertOk()
            ->assertSeeText('Outside selected period')
            ->assertDontSeeText('Reviewed by role 0');
    }
}
