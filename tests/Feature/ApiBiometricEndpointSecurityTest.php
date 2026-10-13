<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Device;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\BiometricDeviceAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ApiBiometricEndpointSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const DEVICE_SECRET = 'test-device-secret-for-attendance-clock';

    private function makeBranch(string $code, string $name): Branch
    {
        return Branch::create([
            'branch_code' => $code,
            'branch_name' => $name,
            'address' => $name . ' Address',
        ]);
    }

    private function makeSignedClockPayload(
        EmployeeProfile $employee,
        Branch $branch,
        Device $device,
        string $fingerprint,
        string $nonce
    ): array {
        $payload = [
            'branch_id' => $branch->id,
            'employee_number' => $employee->employee_number,
            'attendance_type' => 'am_in',
            'fingerprint_data' => $fingerprint,
            'device_serial' => $device->serial_number,
            'mac_address' => 'AA:BB:CC:DD:EE:41',
            'timestamp' => now()->toIso8601String(),
            'nonce' => $nonce,
        ];
        $request = Request::create('/api/attendance/clock', 'POST', $payload);
        $payload['signature'] = hash_hmac(
            'sha256',
            app(BiometricDeviceAuthenticator::class)->signingPayload($request),
            self::DEVICE_SECRET
        );

        return $payload;
    }

    public function test_attendance_clock_rejects_untrusted_devices_and_unmatched_fingerprints(): void
    {
        $branch = $this->makeBranch('BR-CLOCK-SEC', 'Clock Security Branch');
        $employeeUser = User::create([
            'name' => 'Clock Security Employee',
            'email' => 'clock.security.employee@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);
        $employee = EmployeeProfile::create([
            'user_id' => $employeeUser->id,
            'branch_id' => $branch->id,
            'employee_number' => 'EMP-CLOCK-SEC',
            'first_name' => 'Clock Security',
            'last_name' => 'Employee',
            'position' => 'Staff',
            'date_hired' => now()->toDateString(),
            'fingerprint_template' => base64_encode(str_repeat('saved-fingerprint-template', 8)),
            'is_fingerprint_registered' => true,
        ]);
        $device = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-41',
            'serial_number' => 'SECURE-CLOCK-SERIAL-01',
            'api_secret' => self::DEVICE_SECRET,
            'device_name' => 'Secure Clock Test Scanner',
            'device_type' => 'computer',
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);

        $this->postJson('/api/attendance/clock', [
            'branch_id' => $branch->id,
            'employee_number' => $employee->employee_number,
            'attendance_type' => 'am_in',
            'fingerprint_data' => $employee->fingerprint_template,
            'device_serial' => $device->serial_number,
            'mac_address' => 'AA:BB:CC:DD:EE:41',
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'unsigned-clock-attempt',
            'signature' => str_repeat('0', 64),
        ])->assertUnauthorized();

        $this->postJson('/api/attendance/clock', $this->makeSignedClockPayload(
            $employee,
            $branch,
            $device,
            base64_encode(str_repeat('forged-fingerprint-template', 8)),
            'wrong-fingerprint-attempt'
        ))->assertUnauthorized()
            ->assertJson(['message' => 'Fingerprint not recognized']);

        $this->assertDatabaseMissing('attendance_logs', [
            'employee_profile_id' => $employee->id,
            'attendance_date' => now()->toDateString(),
        ]);

        $this->postJson('/api/attendance/clock', $this->makeSignedClockPayload(
            $employee,
            $branch,
            $device,
            $employee->fingerprint_template,
            'valid-fingerprint-clock'
        ))->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('attendance_logs', [
            'employee_profile_id' => $employee->id,
            'attendance_date' => now()->startOfDay()->toDateTimeString(),
            'verification_method' => 'fingerprint',
        ]);
    }

    public function test_fingerprint_temp_requires_admin_access_and_limits_branch_admins_to_their_branch(): void
    {
        $branch = $this->makeBranch('BR-TEMP-SEC', 'Temporary Data Branch');
        $otherBranch = $this->makeBranch('BR-TEMP-OTHER', 'Other Temporary Branch');

        cache()->put('fingerprint_temp_EMP-TEMP-SEC', [
            'employee_number' => 'EMP-TEMP-SEC',
            'fingerprint_data' => str_repeat('sensitive-template-', 10),
            'branch_id' => $branch->id,
        ], 3600);
        cache()->put('fingerprint_temp_EMP-TEMP-OTHER', [
            'employee_number' => 'EMP-TEMP-OTHER',
            'fingerprint_data' => str_repeat('other-sensitive-template-', 10),
            'branch_id' => $otherBranch->id,
        ], 3600);

        $this->getJson('/api/fingerprint-temp?employee_number=EMP-TEMP-SEC')
            ->assertUnauthorized();

        $branchAdmin = User::create([
            'name' => 'Branch Admin',
            'email' => 'branch.admin.temp.security@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'admin_type' => 'branch_admin',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);

        $this->actingAs($branchAdmin)
            ->getJson('/api/fingerprint-temp?employee_number=EMP-TEMP-SEC')
            ->assertOk()
            ->assertJsonPath('data.fingerprint_data', str_repeat('sensitive-template-', 10));

        $this->getJson('/api/fingerprint-temp?employee_number=EMP-TEMP-OTHER')
            ->assertForbidden();
    }

    public function test_non_admin_users_cannot_read_temporary_fingerprint_templates(): void
    {
        $branch = $this->makeBranch('BR-TEMP-USER', 'Temporary User Branch');
        $employee = User::create([
            'name' => 'Regular Employee',
            'email' => 'regular.employee.temp.security@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);

        $this->actingAs($employee)
            ->getJson('/api/fingerprint-temp?employee_number=EMP-TEMP-USER')
            ->assertForbidden();
    }
}
