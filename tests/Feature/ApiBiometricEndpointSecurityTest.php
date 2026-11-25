<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Device;
use App\Models\AttendanceLog;
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
        string $nonce,
        ?string $timestamp = null
    ): array {
        $payload = [
            'branch_id' => $branch->id,
            'employee_number' => $employee->employee_number,
            'attendance_type' => 'am_in',
            'fingerprint_data' => $fingerprint,
            'device_serial' => $device->serial_number,
            'mac_address' => 'AA:BB:CC:DD:EE:41',
            'timestamp' => $timestamp ?? now()->toIso8601String(),
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

    private function makeSignedDeviceQuery(Device $device, string $path): array
    {
        $payload = [
            'device_serial' => $device->serial_number,
            'wifi_mac' => 'AA:BB:CC:DD:EE:42',
            'device_id' => '',
            'mac_address' => 'AA:BB:CC:DD:EE:42',
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'today-dtr-' . bin2hex(random_bytes(8)),
            'reader_id' => '',
        ];
        $request = Request::create('/' . ltrim($path, '/'), 'GET', $payload);
        $payload['signature'] = hash_hmac(
            'sha256',
            app(BiometricDeviceAuthenticator::class)->signingPayload($request),
            self::DEVICE_SECRET
        );

        return $payload;
    }

    public function test_signed_today_dtr_endpoint_returns_attendance_for_the_device_branch(): void
    {
        $branch = $this->makeBranch('BR-DTR-API', 'Daily Attendance API Branch');
        $employeeUser = User::create([
            'name' => 'Daily Attendance Employee',
            'email' => 'daily.attendance.employee@example.com',
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
            'employee_number' => 'EMP-DTR-API',
            'first_name' => 'Daily',
            'last_name' => 'Attendance',
            'position' => 'Staff',
            'date_hired' => today()->toDateString(),
        ]);
        $device = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-42',
            'serial_number' => 'DTR-API-SERIAL-01',
            'api_secret' => self::DEVICE_SECRET,
            'device_name' => 'Daily Attendance API Test Scanner',
            'device_type' => 'computer',
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        AttendanceLog::create([
            'employee_id' => $employeeUser->id,
            'employee_profile_id' => $employee->id,
            'branch_id' => $branch->id,
            'attendance_date' => today(),
            'am_in' => now()->setTime(8, 0),
            'pm_out' => now()->setTime(17, 0),
            'status' => 'present',
        ]);

        $query = $this->makeSignedDeviceQuery($device, 'api/biometric/dtr-today');
        $this->getJson('/api/biometric/dtr-today?' . http_build_query($query))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('records.0.employee_number', $employee->employee_number)
            ->assertJsonPath('records.0.employee_name', 'Daily Attendance')
            ->assertJsonPath('records.0.date', today()->format('m/d/Y'));
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

        $this->postJson('/api/attendance/clock', $this->makeSignedClockPayload(
            $employee,
            $branch,
            $device,
            $employee->fingerprint_template,
            'stale-clock-attempt',
            now()->subMinutes(2)->toIso8601String()
        ))->assertStatus(409)
            ->assertJson(['attendance_blocked' => true]);

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

    public function test_browser_users_cannot_clock_attendance_without_a_registered_kiosk(): void
    {
        $branch = $this->makeBranch('BR-BROWSER-CLOCK-SEC', 'Browser Clock Security Branch');
        $employee = User::create([
            'name' => 'Browser Clock Employee',
            'email' => 'browser.clock.employee@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);
        $payload = [
            'branch_id' => $branch->id,
            'employee_number' => 'EMP-BROWSER-CLOCK-SEC',
            'attendance_type' => 'am_in',
            'fingerprint_data' => base64_encode('browser-fingerprint-data'),
            'device_serial' => 'UNREGISTERED-BROWSER-DEVICE',
            'mac_address' => 'AA:BB:CC:DD:EE:50',
            'device_id' => 'browser-device',
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'browser-clock-attempt',
            'signature' => str_repeat('0', 64),
        ];

        foreach (['/attendance/clock', '/api/attendance/clock'] as $endpoint) {
            $this->actingAs($employee)
                ->postJson($endpoint, $payload)
                ->assertUnauthorized();
        }

        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_attendance_clock_rejects_a_registered_device_outside_its_gps_radius(): void
    {
        $branch = $this->makeBranch('BR-GPS-CLOCK-SEC', 'GPS Clock Security Branch');
        $employeeUser = User::create([
            'name' => 'GPS Clock Employee',
            'email' => 'gps.clock.employee@example.com',
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
            'employee_number' => 'EMP-GPS-CLOCK-SEC',
            'first_name' => 'GPS Clock',
            'last_name' => 'Employee',
            'position' => 'Staff',
            'date_hired' => today()->toDateString(),
            'fingerprint_template' => base64_encode(str_repeat('gps-clock-fingerprint-template', 5)),
            'is_fingerprint_registered' => true,
        ]);
        $device = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-41',
            'serial_number' => 'GPS-CLOCK-SERIAL-01',
            'api_secret' => self::DEVICE_SECRET,
            'device_name' => 'GPS Clock Test Scanner',
            'device_type' => 'computer',
            'branch_id' => $branch->id,
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'radius_meters' => 100,
            'location_check_enabled' => true,
            'status' => 'active',
        ]);

        $payload = $this->makeSignedClockPayload(
            $employee,
            $branch,
            $device,
            $employee->fingerprint_template,
            'outside-campus-gps-attempt'
        );
        $payload['gps_latitude'] = '14.61';
        $payload['gps_longitude'] = '120.9842';
        $payload['gps_accuracy'] = '5';
        $payload['gps_timestamp'] = now()->toIso8601String();
        $request = Request::create('/api/attendance/clock', 'POST', $payload);
        $payload['signature'] = hash_hmac(
            'sha256',
            app(BiometricDeviceAuthenticator::class)->signingPayload($request, true),
            self::DEVICE_SECRET
        );

        $tamperedPayload = $payload;
        $tamperedPayload['gps_latitude'] = '14.5995';
        $this->postJson('/api/attendance/clock', $tamperedPayload)
            ->assertUnauthorized()
            ->assertJson(['message' => 'Invalid device credential signature.']);

        $response = $this->postJson('/api/attendance/clock', $payload);
        $this->assertSame(403, $response->getStatusCode(), $response->getContent());
        $response
            ->assertJsonPath('attendance_blocked', true)
            ->assertJsonPath('message', 'The kiosk is outside the school attendance area. Attendance was not recorded.');

        $this->assertDatabaseMissing('attendance_logs', [
            'employee_profile_id' => $employee->id,
            'attendance_date' => now()->toDateString(),
        ]);
    }

    public function test_fingerprint_enrollment_rejects_a_registered_device_outside_its_gps_radius(): void
    {
        $branch = $this->makeBranch('BR-GPS-ENROLL-SEC', 'GPS Enrollment Security Branch');
        $device = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-43',
            'serial_number' => 'GPS-ENROLL-SERIAL-01',
            'api_secret' => self::DEVICE_SECRET,
            'device_name' => 'GPS Enrollment Test Scanner',
            'device_type' => 'computer',
            'branch_id' => $branch->id,
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'radius_meters' => 100,
            'location_check_enabled' => true,
            'status' => 'active',
        ]);
        $payload = [
            'employee_number' => 'EMP-GPS-ENROLL-01',
            'fingerprint_data' => base64_encode(str_repeat('enrollment-fingerprint-template', 5)),
            'first_name' => 'GPS',
            'last_name' => 'Enrollment',
            'branch' => $branch->branch_name,
            'device_serial' => $device->serial_number,
            'wifi_mac' => 'AA:BB:CC:DD:EE:43',
            'device_id' => '',
            'mac_address' => 'AA:BB:CC:DD:EE:43',
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'outside-campus-gps-enrollment-attempt',
            'gps_latitude' => '14.61',
            'gps_longitude' => '120.9842',
            'gps_accuracy' => '5',
            'gps_timestamp' => now()->toIso8601String(),
        ];
        $request = Request::create('/api/fingerprint-register-temp', 'POST', $payload);
        $payload['signature'] = hash_hmac(
            'sha256',
            app(BiometricDeviceAuthenticator::class)->signingPayload($request, true),
            self::DEVICE_SECRET
        );

        $this->postJson('/api/fingerprint-register-temp', $payload)
            ->assertForbidden()
            ->assertJsonPath('enrollment_blocked', true)
            ->assertJsonPath('message', 'The kiosk is outside the configured school area. Fingerprint enrollment was not completed.');

        $this->assertNull(cache()->get('fingerprint_temp_EMP-GPS-ENROLL-01'));
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

    public function test_bridge_status_reads_enrollment_executable_from_config(): void
    {
        config(['services.mtcgs_enroll.exe' => 'C:\\enrollment\\MTCGS_Enroll.exe']);

        $this->getJson('/api/biometric/bridge-status')
            ->assertOk()
            ->assertJsonPath('enrollment_exe', 'C:\\enrollment\\MTCGS_Enroll.exe');
    }

    public function test_signed_device_status_returns_the_registered_geofence_settings(): void
    {
        $branch = $this->makeBranch('BR-GPS-STATUS', 'GPS Status Branch');
        $device = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-42',
            'serial_number' => 'GPS-STATUS-SERIAL-01',
            'api_secret' => self::DEVICE_SECRET,
            'device_name' => 'GPS Status Test Device',
            'device_type' => 'computer',
            'branch_id' => $branch->id,
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'radius_meters' => 391,
            'location_check_enabled' => true,
            'status' => 'active',
        ]);
        $query = $this->makeSignedDeviceQuery($device, 'api/device-status');

        $this->getJson('/api/device-status?' . http_build_query($query))
            ->assertOk()
            ->assertJsonPath('device.location_check_enabled', true)
            ->assertJsonPath('device.latitude', 14.5995)
            ->assertJsonPath('device.longitude', 120.9842)
            ->assertJsonPath('device.radius_meters', 391);
    }
}
