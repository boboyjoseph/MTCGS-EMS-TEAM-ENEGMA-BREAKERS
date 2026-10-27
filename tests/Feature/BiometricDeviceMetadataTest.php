<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\BiometricController;
use App\Http\Controllers\Api\AttendanceController;
use App\Models\AttendanceLog;
use App\Models\AdminProfile;
use App\Models\Branch;
use App\Models\CalendarEvent;
use App\Models\Device;
use App\Models\EmployeeProfile;
use App\Models\FinanceProfile;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\BiometricDeviceAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class BiometricDeviceMetadataTest extends TestCase
{
    use RefreshDatabase;

    private const DEVICE_SECRET = 'test-biometric-device-secret-for-signed-requests';

    private function legacySignature(array $payload): string
    {
        return hash_hmac('sha256', implode('|', [
            $payload['employee_number'] ?? null,
            $payload['device_serial'] ?? null,
            $payload['wifi_mac'] ?? null,
            $payload['device_id'] ?? null,
            $payload['mac_address'] ?? null,
            $payload['timestamp'] ?? null,
            $payload['nonce'] ?? null,
        ]), 'mtcgs-2026-secure-device-hmac-7c7fba18a9b2');
    }

    private function signedDevicePayload(array $payload, string $method, string $path): array
    {
        $values = [
            strtoupper($method),
            ltrim($path, '/'),
            $payload['employee_id'] ?? null,
            $payload['employee_number'] ?? null,
            $payload['device_serial'] ?? null,
            $payload['wifi_mac'] ?? null,
            $payload['device_id'] ?? null,
            $payload['mac_address'] ?? null,
            $payload['timestamp'] ?? null,
            $payload['nonce'] ?? null,
            $payload['action'] ?? null,
            $payload['fingerprint_data'] ?? null,
        ];
        if (isset($payload['finger_name']) && trim((string) $payload['finger_name']) !== '') {
            $values[] = $payload['finger_name'];
        }
        $values[] = $payload['branch'] ?? null;
        $values[] = $payload['attendance_timestamp'] ?? null;
        if (isset($payload['fingerprint_scope']) && trim((string) $payload['fingerprint_scope']) !== '') {
            $values[] = $payload['fingerprint_scope'];
        }
        $values[] = $payload['reader_id'] ?? null;
        $payload['signature'] = hash_hmac(
            'sha256',
            implode('|', array_map(static fn ($value) => is_scalar($value) ? (string) $value : '', $values)),
            self::DEVICE_SECRET
        );

        return $payload;
    }

    public function test_fingerprint_verification_does_not_record_attendance(): void
    {
        $branch = Branch::create([
            'branch_code' => 'BR-VERIFY-ONLY',
            'branch_name' => 'Verify Only Branch',
            'address' => 'Verify Only Address',
        ]);
        $user = User::create([
            'name' => 'Verify Only',
            'email' => 'verify-only@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);
        $employee = EmployeeProfile::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'employee_number' => 'EMP-VERIFY-ONLY',
            'first_name' => 'Verify',
            'last_name' => 'Only',
            'position' => 'Staff',
            'date_hired' => now(),
            'fingerprint_template' => base64_encode(str_repeat('real-fingerprint-template', 10)),
            'is_fingerprint_registered' => true,
        ]);
        $device = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-51',
            'laptop_mac_address' => 'VERIFY-ONLY-DEVICE',
            'serial_number' => 'J62OE23640',
            'reader_id' => '70315bbe-3635-8b41-b4af-04a0bcd6e941',
            'api_secret' => self::DEVICE_SECRET,
            'device_name' => 'Verify Only Scanner',
            'device_type' => 'computer',
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $payload = $this->signedDevicePayload([
            'fingerprint_data' => $employee->fingerprint_template,
            'device_serial' => $device->serial_number,
            'reader_id' => $device->reader_id,
            'wifi_mac' => 'AA:BB:CC:DD:EE:51',
            'device_id' => $device->laptop_mac_address,
            'mac_address' => 'AA:BB:CC:DD:EE:51',
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'verify-only-no-attendance',
        ], 'POST', '/api/biometric/verify');

        $response = (new BiometricController())->verifyFingerprint(
            Request::create('/api/biometric/verify', 'POST', $payload)
        );

        $this->assertSame(200, $response->getStatusCode(), $response->content());
        $this->assertTrue(json_decode($response->content(), true)['success']);
        $this->assertFalse(json_decode($response->content(), true)['attendance_recorded']);
        $this->assertTrue(json_decode($response->content(), true)['requires_explicit_action']);
        $this->assertDatabaseMissing('attendance_logs', [
            'employee_profile_id' => $employee->id,
            'attendance_date' => now()->toDateString(),
        ]);
    }

    public function test_admin_biometric_page_renders_profile_data_without_blade_parse_error(): void
    {
        $branch = Branch::create([
            'branch_code' => 'BR-BIOMETRIC-PAGE',
            'branch_name' => 'Biometric Page Branch',
            'address' => 'Biometric Page Address',
        ]);
        $admin = User::create([
            'name' => 'Biometric Page Admin',
            'email' => 'biometric-page-admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'admin_type' => 'super_admin',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);
        AdminProfile::create([
            'user_id' => $admin->id,
            'branch_id' => $branch->id,
            'employee_number' => 'ADM-BIOMETRIC-PAGE',
            'first_name' => 'Biometric',
            'last_name' => 'Admin',
            'position' => 'System Administrator',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.biometric'))
            ->assertOk()
            ->assertSeeText('Biometric Management');
    }

    public function test_super_admin_cannot_register_own_fingerprint_and_card_is_hidden(): void
    {
        $branch = Branch::create([
            'branch_code' => 'BR-SUPERADMIN-BIOMETRIC',
            'branch_name' => 'Super Admin Biometric Branch',
            'address' => 'Super Admin Biometric Address',
        ]);
        $admin = User::create([
            'name' => 'System Administrator',
            'email' => 'superadmin-biometric@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'admin_type' => 'super_admin',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);
        $existingFingerprint = base64_encode(str_repeat('keep-existing-super-admin-template', 8));
        $adminProfile = AdminProfile::create([
            'user_id' => $admin->id,
            'branch_id' => $branch->id,
            'employee_number' => 'SUPERADMIN-BIOMETRIC-001',
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'position' => 'System Administrator',
            'admin_level' => 'super_admin',
            'fingerprint_template' => $existingFingerprint,
            'is_fingerprint_registered' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.biometric'))
            ->assertOk()
            ->assertDontSee('My Admin Fingerprint')
            ->assertDontSee('Register My Fingerprint');

        $this->postJson('/api/biometric/register-admin', [
            'fingerprint_data' => base64_encode(str_repeat('new-super-admin-template', 8)),
        ])->assertForbidden();

        $this->assertDatabaseHas('admin_profiles', [
            'id' => $adminProfile->id,
            'fingerprint_template' => $existingFingerprint,
            'is_fingerprint_registered' => true,
        ]);
    }

    public function test_hr_admin_cannot_register_or_use_fingerprint_attendance(): void
    {
        $branch = Branch::create([
            'branch_code' => 'BR-HR-BIOMETRIC',
            'branch_name' => 'HR Biometric Branch',
            'address' => 'HR Biometric Address',
        ]);
        $hr = User::create([
            'name' => 'HR Administrator',
            'email' => 'hr-biometric@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'admin_type' => 'hr',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);
        $savedFingerprint = base64_encode(str_repeat('hr-fingerprint-stays-untouched', 8));
        $adminProfile = AdminProfile::create([
            'user_id' => $hr->id,
            'branch_id' => $branch->id,
            'employee_number' => 'HR-BIOMETRIC-001',
            'first_name' => 'HR',
            'last_name' => 'Administrator',
            'position' => 'Human Resources',
            'admin_level' => 'hr',
            'fingerprint_template' => $savedFingerprint,
            'is_fingerprint_registered' => true,
        ]);
        $hrEmployee = EmployeeProfile::create([
            'user_id' => $hr->id,
            'branch_id' => $branch->id,
            'employee_number' => 'HR-EMP-BIOMETRIC-001',
            'first_name' => 'HR',
            'last_name' => 'Administrator',
            'position' => 'Human Resources',
            'date_hired' => '2024-01-01',
            'fingerprint_template' => $savedFingerprint,
            'is_fingerprint_registered' => true,
        ]);
        $device = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-73',
            'laptop_mac_address' => 'HR-BIOMETRIC-DEVICE',
            'serial_number' => 'SERIAL-HR-BIOMETRIC-001',
            'reader_id' => '70315bbe-3635-8b41-b4af-04a0bcd6e941',
            'api_secret' => self::DEVICE_SECRET,
            'device_name' => 'HR Biometric Test Scanner',
            'device_type' => 'computer',
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);

        $this->actingAs($hr)
            ->get(route('admin.biometric'))
            ->assertOk()
            ->assertDontSeeText('My Admin Fingerprint')
            ->assertDontSee('<button id="registerAdminFingerprintButton"', false);

        $this->getJson('/api/biometric/unregistered-employees')
            ->assertOk()
            ->assertJsonPath('count', 0);

        $this->postJson('/api/biometric/register', [
            'employee_id' => $hrEmployee->id,
            'fingerprint_data' => base64_encode('new-hr-employee-fingerprint'),
        ])->assertUnprocessable();
        $this->postJson('/api/biometric/register-admin', [
            'fingerprint_data' => base64_encode('new-hr-fingerprint'),
        ])->assertForbidden();
        $this->getJson('/api/biometric/admin-status')->assertForbidden();
        $this->postJson('/api/biometric/admin-attendance', [
            'fingerprint_data' => $savedFingerprint,
        ])->assertForbidden();
        $this->postJson('/api/biometric/process', [
            'fingerprint_data' => $savedFingerprint,
        ])->assertForbidden();

        $templatePayload = $this->signedDevicePayload([
            'device_serial' => $device->serial_number,
            'reader_id' => $device->reader_id,
            'wifi_mac' => $device->mac_address,
            'device_id' => $device->laptop_mac_address,
            'mac_address' => $device->mac_address,
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'hr-biometric-templates',
        ], 'GET', '/api/biometric/templates');
        $templateResponse = (new BiometricController())->getFingerprintTemplates(
            Request::create('/api/biometric/templates', 'GET', $templatePayload)
        );
        $this->assertSame(200, $templateResponse->getStatusCode(), $templateResponse->getContent());
        $this->assertSame([], json_decode($templateResponse->getContent(), true)['templates']);

        $verifyPayload = $this->signedDevicePayload([
            'fingerprint_data' => $savedFingerprint,
            'device_serial' => $device->serial_number,
            'reader_id' => $device->reader_id,
            'wifi_mac' => $device->mac_address,
            'device_id' => $device->laptop_mac_address,
            'mac_address' => $device->mac_address,
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'hr-biometric-verify',
        ], 'POST', '/api/biometric/verify');
        $verifyResponse = (new BiometricController())->verifyFingerprint(
            Request::create('/api/biometric/verify', 'POST', $verifyPayload)
        );
        $this->assertSame(404, $verifyResponse->getStatusCode(), $verifyResponse->getContent());

        $this->assertDatabaseHas('admin_profiles', [
            'id' => $adminProfile->id,
            'fingerprint_template' => $savedFingerprint,
            'is_fingerprint_registered' => true,
        ]);
        $this->assertDatabaseHas('employee_profiles', [
            'id' => $hrEmployee->id,
            'fingerprint_template' => $savedFingerprint,
            'is_fingerprint_registered' => true,
        ]);
        $this->assertDatabaseMissing('attendance_logs', [
            'employee_profile_id' => $hrEmployee->id,
        ]);
    }

    public function test_biometric_employee_status_list_keeps_registered_users_visible(): void
    {
        $branch = Branch::create([
            'branch_code' => 'BR-BIOMETRIC-ALL-USERS',
            'branch_name' => 'Biometric All Users Branch',
            'address' => 'Biometric All Users Address',
        ]);
        $admin = User::create([
            'name' => 'Biometric List Admin',
            'email' => 'biometric-list-admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'admin_type' => 'super_admin',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);
        $registeredUser = User::factory()->create(['branch_id' => $branch->id]);
        $registeredEmployee = EmployeeProfile::create([
            'user_id' => $registeredUser->id,
            'branch_id' => $branch->id,
            'employee_number' => 'EMP-BIOMETRIC-REGISTERED',
            'first_name' => 'Registered',
            'last_name' => 'Employee',
            'position' => 'Teacher',
            'date_hired' => '2024-01-01',
            'fingerprint_template' => base64_encode(str_repeat('registered-template', 8)),
            'is_fingerprint_registered' => true,
        ]);
        $unregisteredUser = User::factory()->create(['branch_id' => $branch->id]);
        $unregisteredEmployee = EmployeeProfile::create([
            'user_id' => $unregisteredUser->id,
            'branch_id' => $branch->id,
            'employee_number' => 'EMP-BIOMETRIC-UNREGISTERED',
            'first_name' => 'Unregistered',
            'last_name' => 'Employee',
            'position' => 'Staff',
            'date_hired' => '2024-01-01',
            'is_fingerprint_registered' => false,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/biometric/unregistered-employees');

        $response->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonFragment([
                'id' => $registeredEmployee->id,
                'is_fingerprint_registered' => true,
            ])
            ->assertJsonFragment([
                'id' => $unregisteredEmployee->id,
                'is_fingerprint_registered' => false,
            ]);
    }

    public function test_offline_sync_recognizes_saved_template_when_registration_flag_is_stale(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-10-12 08:00:00');

        $branch = Branch::create([
            'branch_code' => 'BR-OFFLINE-SYNC',
            'branch_name' => 'Offline Sync Branch',
            'address' => 'Offline Sync Address',
        ]);
        $user = User::create([
            'name' => 'Offline Sync Employee',
            'email' => 'offline-sync@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);
        $fingerprint = base64_encode(str_repeat('offline-sync-template-', 8));
        $readerId = '70315bbe-3635-8b41-b4af-04a0bcd6e941';
        $employee = EmployeeProfile::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'employee_number' => 'EMP-OFFLINE-SYNC',
            'first_name' => 'Offline',
            'last_name' => 'Sync',
            'position' => 'Staff',
            'date_hired' => now(),
            'fingerprint_template' => $fingerprint,
            'is_fingerprint_registered' => false,
        ]);
        $device = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-61',
            'laptop_mac_address' => 'OFFLINE-SYNC-DEVICE',
            'serial_number' => 'J62OE23640',
            'reader_id' => '70315bbe-3635-8b41-b4af-04a0bcd6e941',
            'api_secret' => self::DEVICE_SECRET,
            'device_name' => 'Offline Sync Scanner',
            'device_type' => 'computer',
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $payload = $this->signedDevicePayload([
            'employee_number' => $employee->employee_number,
            'fingerprint_data' => $fingerprint,
            'action' => 'TIME-IN',
            'device_serial' => $device->serial_number,
            'reader_id' => $readerId,
            'wifi_mac' => 'AA:BB:CC:DD:EE:61',
            'device_id' => $device->laptop_mac_address,
            'mac_address' => 'AA:BB:CC:DD:EE:61',
            'timestamp' => now()->toIso8601String(),
            'attendance_timestamp' => now()->toIso8601String(),
            'nonce' => 'offline-sync-stale-registered-flag',
        ], 'POST', '/api/biometric/time-clock');

        try {
            $response = (new BiometricController())->processTimeClock(
                Request::create('/api/biometric/time-clock', 'POST', $payload)
            );

            $this->assertSame(200, $response->getStatusCode(), $response->content());
            $this->assertTrue(json_decode($response->content(), true)['success']);
            $this->assertDatabaseHas('attendance_logs', [
                'employee_profile_id' => $employee->id,
                'attendance_date' => now()->startOfDay()->toDateTimeString(),
            ]);

            $savedTimeIn = AttendanceLog::where('employee_profile_id', $employee->id)
                ->whereDate('attendance_date', now()->toDateString())
                ->firstOrFail()
                ->am_in
                ->toDateTimeString();
            $this->assertSame('2026-10-12 08:00:00', $savedTimeIn);
            $retryPayload = $this->signedDevicePayload([
                'employee_number' => $employee->employee_number,
                'fingerprint_data' => $fingerprint,
                'action' => 'TIME-IN',
                'device_serial' => $device->serial_number,
                'reader_id' => $readerId,
                'wifi_mac' => 'AA:BB:CC:DD:EE:61',
                'device_id' => $device->laptop_mac_address,
                'mac_address' => 'AA:BB:CC:DD:EE:61',
                'timestamp' => now()->toIso8601String(),
                'attendance_timestamp' => now()->toIso8601String(),
                'nonce' => 'offline-sync-time-in-retry',
            ], 'POST', '/api/biometric/time-clock');
            $retryResponse = (new BiometricController())->processTimeClock(
                Request::create('/api/biometric/time-clock', 'POST', $retryPayload)
            );

            $this->assertSame(200, $retryResponse->getStatusCode(), $retryResponse->content());
            $this->assertTrue(json_decode($retryResponse->content(), true)['already_recorded']);
            $this->assertSame('08:00 AM', json_decode($retryResponse->content(), true)['data']['action_time']);
            $this->assertSame(
                '2026-10-12 08:00:00',
                AttendanceLog::where('employee_profile_id', $employee->id)
                    ->whereDate('attendance_date', now()->toDateString())
                    ->firstOrFail()
                    ->am_in
                    ->toDateTimeString()
            );

            $staleRetryPayload = $this->signedDevicePayload([
                'employee_number' => $employee->employee_number,
                'fingerprint_data' => $fingerprint,
                'action' => 'TIME-IN',
                'device_serial' => $device->serial_number,
                'reader_id' => $readerId,
                'wifi_mac' => 'AA:BB:CC:DD:EE:61',
                'device_id' => $device->laptop_mac_address,
                'mac_address' => 'AA:BB:CC:DD:EE:61',
                'timestamp' => now()->toIso8601String(),
                'attendance_timestamp' => now()->addMinutes(4)->toIso8601String(),
                'nonce' => 'offline-sync-stale-time-in-retry',
            ], 'POST', '/api/biometric/time-clock');
            $staleRetryResponse = (new BiometricController())->processTimeClock(
                Request::create('/api/biometric/time-clock', 'POST', $staleRetryPayload)
            );
            $this->assertSame(409, $staleRetryResponse->getStatusCode(), $staleRetryResponse->content());
            $this->assertStringContainsString('Offline attendance is disabled', json_decode($staleRetryResponse->content(), true)['error']);
            $this->assertSame(
                '2026-10-12 08:00:00',
                AttendanceLog::where('employee_profile_id', $employee->id)
                    ->whereDate('attendance_date', now()->toDateString())
                    ->firstOrFail()
                    ->am_in
                    ->toDateTimeString()
            );

            \Illuminate\Support\Carbon::setTestNow(now()->addMinutes(10));
            $onlineUpdatePayload = $this->signedDevicePayload([
                'employee_number' => $employee->employee_number,
                'fingerprint_data' => $fingerprint,
                'action' => 'TIME-IN',
                'device_serial' => $device->serial_number,
                'reader_id' => $readerId,
                'wifi_mac' => 'AA:BB:CC:DD:EE:61',
                'device_id' => $device->laptop_mac_address,
                'mac_address' => 'AA:BB:CC:DD:EE:61',
                'timestamp' => now()->toIso8601String(),
                'attendance_timestamp' => now()->toIso8601String(),
                'nonce' => 'online-time-in-updates-saved-time',
            ], 'POST', '/api/biometric/time-clock');
            $onlineUpdateResponse = (new BiometricController())->processTimeClock(
                Request::create('/api/biometric/time-clock', 'POST', $onlineUpdatePayload)
            );
            $onlineUpdateResponseData = json_decode($onlineUpdateResponse->content(), true);

            $this->assertSame(200, $onlineUpdateResponse->getStatusCode(), $onlineUpdateResponse->content());
            $this->assertFalse($onlineUpdateResponseData['already_recorded'] ?? false);
            $this->assertSame('08:10 AM', $onlineUpdateResponseData['data']['time_in']);
            $this->assertSame(
                '2026-10-12 08:10:00',
                AttendanceLog::where('employee_profile_id', $employee->id)
                    ->whereDate('attendance_date', now()->toDateString())
                    ->firstOrFail()
                    ->am_in
                    ->toDateTimeString()
            );

            \Illuminate\Support\Carbon::setTestNow(now()->setTime(10, 16));
            $serverTimeOutPayload = $this->signedDevicePayload([
                    'employee_number' => $employee->employee_number,
                    'fingerprint_data' => $fingerprint,
                    'action' => 'TIME-OUT',
                    'device_serial' => $device->serial_number,
                    'reader_id' => $readerId,
                    'wifi_mac' => 'AA:BB:CC:DD:EE:61',
                    'device_id' => $device->laptop_mac_address,
                    'mac_address' => 'AA:BB:CC:DD:EE:61',
                    'timestamp' => now()->toIso8601String(),
                    'attendance_timestamp' => now()->toIso8601String(),
                    'nonce' => 'offline-sync-server-timeout',
                ], 'POST', '/api/biometric/time-clock');
            $serverTimeOutResponse = (new BiometricController())->processTimeClock(
                Request::create('/api/biometric/time-clock', 'POST', $serverTimeOutPayload)
            );
            $this->assertSame(200, $serverTimeOutResponse->getStatusCode(), $serverTimeOutResponse->content());

            $offlineTimeOutPayload = $this->signedDevicePayload([
                    'employee_number' => $employee->employee_number,
                    'fingerprint_data' => $fingerprint,
                    'action' => 'TIME-OUT',
                    'device_serial' => $device->serial_number,
                    'reader_id' => $readerId,
                    'wifi_mac' => 'AA:BB:CC:DD:EE:61',
                    'device_id' => $device->laptop_mac_address,
                    'mac_address' => 'AA:BB:CC:DD:EE:61',
                    'timestamp' => now()->toIso8601String(),
                    'attendance_timestamp' => now()->setTime(10, 7)->toIso8601String(),
                    'nonce' => 'offline-sync-earlier-timeout',
                ], 'POST', '/api/biometric/time-clock');
            $offlineTimeOutResponse = (new BiometricController())->processTimeClock(
                Request::create('/api/biometric/time-clock', 'POST', $offlineTimeOutPayload)
            );
            $this->assertSame(409, $offlineTimeOutResponse->getStatusCode(), $offlineTimeOutResponse->content());
            $this->assertStringContainsString('Offline attendance is disabled', json_decode($offlineTimeOutResponse->content(), true)['error']);
            $this->assertSame(
                '2026-10-12 10:16:00',
                AttendanceLog::where('employee_profile_id', $employee->id)
                    ->whereDate('attendance_date', now()->toDateString())
                    ->firstOrFail()
                    ->pm_out
                    ->toDateTimeString()
            );

            \Illuminate\Support\Carbon::setTestNow(now()->setTime(10, 17));
            $onlineTimeOutPayload = $this->signedDevicePayload([
                'employee_number' => $employee->employee_number,
                'fingerprint_data' => $fingerprint,
                'action' => 'TIME-OUT',
                'device_serial' => $device->serial_number,
                'reader_id' => $readerId,
                'wifi_mac' => 'AA:BB:CC:DD:EE:61',
                'device_id' => $device->laptop_mac_address,
                'mac_address' => 'AA:BB:CC:DD:EE:61',
                'timestamp' => now()->toIso8601String(),
                'attendance_timestamp' => now()->toIso8601String(),
                'nonce' => 'online-time-out-updates-saved-time',
            ], 'POST', '/api/biometric/time-clock');
            $onlineTimeOutResponse = (new BiometricController())->processTimeClock(
                Request::create('/api/biometric/time-clock', 'POST', $onlineTimeOutPayload)
            );
            $onlineTimeOutResponseData = json_decode($onlineTimeOutResponse->content(), true);

            $this->assertSame(200, $onlineTimeOutResponse->getStatusCode(), $onlineTimeOutResponse->content());
            $this->assertFalse($onlineTimeOutResponseData['already_recorded'] ?? false);
            $this->assertSame('10:17 AM', $onlineTimeOutResponseData['data']['time_out']);
            $this->assertSame(
                '2026-10-12 10:17:00',
                AttendanceLog::where('employee_profile_id', $employee->id)
                    ->whereDate('attendance_date', now()->toDateString())
                    ->firstOrFail()
                    ->pm_out
                    ->toDateTimeString()
            );
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
        }
    }

    public function test_authenticated_buhi_device_uses_its_registered_branch_for_new_enrollment(): void
    {
        $branch = Branch::create([
            'branch_code' => 'BR-ENROLL-BUHI',
            'branch_name' => 'Buhi',
            'address' => 'Buhi Test Address',
        ]);
        $device = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-FF',
            'laptop_mac_address' => 'BUHI-ENROLLMENT-PC-01',
            'serial_number' => 'SERIAL-ENROLL-BUHI-001',
            'api_secret' => self::DEVICE_SECRET,
            'device_name' => 'Buhi Enrollment Scanner',
            'device_type' => 'computer',
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);

        $payload = $this->signedDevicePayload([
            'employee_number' => 'NEW-BUHI-EMPLOYEE',
            'fingerprint_data' => base64_encode(str_repeat('valid-template-data', 10)),
            'first_name' => 'New',
            'last_name' => 'Employee',
            'branch' => 'Buhi Branch',
            'device_serial' => $device->serial_number,
            'wifi_mac' => 'AA:BB:CC:DD:EE:FF',
            'device_id' => $device->laptop_mac_address,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'new-buhi-enrollment-nonce',
        ], 'POST', '/api/fingerprint-register-temp');

        $response = (new BiometricController())->registerFingerprintTemp(
            Request::create('/api/fingerprint-register-temp', 'POST', $payload)
        );

        $this->assertSame(200, $response->getStatusCode(), $response->content());
        $this->assertFalse(json_decode($response->content(), true)['database_saved']);
        $this->assertSame(
            'Buhi',
            cache()->get('fingerprint_temp_NEW-BUHI-EMPLOYEE')['branch']
        );
        $this->assertFalse(cache()->get('fingerprint_temp_NEW-BUHI-EMPLOYEE')['database_saved']);
    }

    public function test_known_branch_scanner_requires_the_registered_device_id(): void
    {
        $branch = Branch::create([
            'branch_code' => 'BR-DEVICE-ID-BUHI',
            'branch_name' => 'Buhi',
            'address' => 'Buhi Device ID Test Address',
        ]);
        $device = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-21',
            'laptop_mac_address' => 'BUHI-DEVICE-ID-01',
            'serial_number' => 'J320E01822',
            'reader_id' => '8c540906-f99a-4e4f-baec-99462e3c24bc',
            'api_secret' => self::DEVICE_SECRET,
            'device_name' => 'Buhi Device ID Test Scanner',
            'device_type' => 'computer',
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);

        foreach ([
            ['device_id' => 'BUHI-DEVICE-ID-01', 'nonce' => 'known-device-id-valid', 'expected' => 200],
            ['device_id' => 'OTHER-WORKSTATION', 'nonce' => 'known-device-id-wrong', 'expected' => 401],
            ['device_id' => '', 'nonce' => 'known-device-id-missing', 'expected' => 401],
            ['device_id' => 'BUHI-DEVICE-ID-01', 'reader_id' => '70315bbe-3635-8b41-b4af-04a0bcd6e941', 'nonce' => 'known-reader-id-wrong', 'expected' => 401],
            ['device_id' => 'BUHI-DEVICE-ID-01', 'reader_id' => '', 'nonce' => 'known-reader-id-missing', 'expected' => 401],
        ] as $case) {
            $payload = $this->signedDevicePayload([
                'employee_number' => 'DEVICE-ID-EMPLOYEE',
                'fingerprint_data' => base64_encode(str_repeat('device-id-template', 10)),
                'first_name' => 'Device',
                'last_name' => 'ID Test',
                'branch' => 'Buhi',
                'device_serial' => $device->serial_number,
                'wifi_mac' => 'AA:BB:CC:DD:EE:21',
                'device_id' => $case['device_id'],
                'reader_id' => $case['reader_id'] ?? '8c540906-f99a-4e4f-baec-99462e3c24bc',
                'mac_address' => 'AA:BB:CC:DD:EE:21',
                'timestamp' => now()->toIso8601String(),
                'nonce' => $case['nonce'],
            ], 'POST', '/api/fingerprint-register-temp');

            $response = (new BiometricController())->registerFingerprintTemp(
                Request::create('/api/fingerprint-register-temp', 'POST', $payload)
            );

            $this->assertSame($case['expected'], $response->getStatusCode(), $response->content());
        }

        $wrongBranchPayload = $this->signedDevicePayload([
            'employee_number' => 'NEW-IRIGA-EMPLOYEE',
            'fingerprint_data' => base64_encode(str_repeat('wrong-branch-template', 10)),
            'first_name' => 'New',
            'last_name' => 'Iriga Employee',
            'branch' => 'Iriga',
            'device_serial' => $device->serial_number,
            'wifi_mac' => 'AA:BB:CC:DD:EE:21',
            'device_id' => $device->laptop_mac_address,
            'reader_id' => '8c540906-f99a-4e4f-baec-99462e3c24bc',
            'mac_address' => 'AA:BB:CC:DD:EE:21',
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'buhi-device-rejects-iriga-enrollment',
        ], 'POST', '/api/fingerprint-register-temp');
        $wrongBranchResponse = (new BiometricController())->registerFingerprintTemp(
            Request::create('/api/fingerprint-register-temp', 'POST', $wrongBranchPayload)
        );
        $this->assertSame(403, $wrongBranchResponse->getStatusCode(), $wrongBranchResponse->content());
        $this->assertStringContainsString('scanner is assigned to Buhi', json_decode($wrongBranchResponse->content(), true)['message']);
        $this->assertNull(cache()->get('fingerprint_temp_NEW-IRIGA-EMPLOYEE'));

        $partialNameBranch = Branch::create([
            'branch_code' => 'BR-ENROLL-RIGA',
            'branch_name' => 'Riga',
            'address' => 'Riga Test Address',
        ]);
        $partialNameDevice = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-23',
            'laptop_mac_address' => 'RIGA-ENROLLMENT-PC-01',
            'serial_number' => 'SERIAL-ENROLL-RIGA-001',
            'api_secret' => self::DEVICE_SECRET,
            'device_name' => 'Riga Enrollment Scanner',
            'device_type' => 'computer',
            'branch_id' => $partialNameBranch->id,
            'status' => 'active',
        ]);
        $partialNamePayload = $this->signedDevicePayload([
            'employee_number' => 'NEW-IRIGA-PARTIAL-MATCH',
            'fingerprint_data' => base64_encode(str_repeat('partial-match-template', 10)),
            'first_name' => 'New',
            'last_name' => 'Iriga Employee',
            'branch' => 'Iriga',
            'device_serial' => $partialNameDevice->serial_number,
            'wifi_mac' => 'AA:BB:CC:DD:EE:23',
            'device_id' => $partialNameDevice->laptop_mac_address,
            'mac_address' => 'AA:BB:CC:DD:EE:23',
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'riga-scanner-rejects-iriga-enrollment',
        ], 'POST', '/api/fingerprint-register-temp');
        $partialNameResponse = (new BiometricController())->registerFingerprintTemp(
            Request::create('/api/fingerprint-register-temp', 'POST', $partialNamePayload)
        );
        $this->assertSame(403, $partialNameResponse->getStatusCode(), $partialNameResponse->content());

        $deviceWithoutIdentifier = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-22',
            'serial_number' => 'J62OE23640',
            'reader_id' => '70315bbe-3635-8b41-b4af-04a0bcd6e941',
            'api_secret' => self::DEVICE_SECRET,
            'device_name' => 'Unprovisioned Iriga Scanner',
            'device_type' => 'computer',
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $payloadWithoutRegisteredId = $this->signedDevicePayload([
            'employee_number' => 'DEVICE-ID-EMPLOYEE-2',
            'fingerprint_data' => base64_encode(str_repeat('device-id-template', 10)),
            'device_serial' => $deviceWithoutIdentifier->serial_number,
            'wifi_mac' => 'AA:BB:CC:DD:EE:22',
            'device_id' => 'UNREGISTERED-CLIENT-ID',
            'reader_id' => '70315bbe-3635-8b41-b4af-04a0bcd6e941',
            'mac_address' => 'AA:BB:CC:DD:EE:22',
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'known-device-id-unprovisioned',
        ], 'POST', '/api/fingerprint-register-temp');

        $responseWithoutRegisteredId = (new BiometricController())->registerFingerprintTemp(
            Request::create('/api/fingerprint-register-temp', 'POST', $payloadWithoutRegisteredId)
        );

        $this->assertSame(401, $responseWithoutRegisteredId->getStatusCode(), $responseWithoutRegisteredId->content());
    }

    public function test_localhost_scanner_requests_also_require_valid_device_signatures(): void
    {
        $this->app['env'] = 'local';
        $branch = Branch::create([
            'branch_code' => 'BR-LOCAL-ENROLL',
            'branch_name' => 'Iriga',
            'address' => 'Iriga Local Enrollment Test Address',
        ]);
        $device = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-31',
            'laptop_mac_address' => 'IRIGA-LOCAL-PC-01',
            'serial_number' => 'J62OE23640',
            'reader_id' => '70315bbe-3635-8b41-b4af-04a0bcd6e941',
            'api_secret' => self::DEVICE_SECRET,
            'device_name' => 'Local Iriga Enrollment Scanner',
            'device_type' => 'computer',
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);

        $makePayload = function (string $nonce) use ($device) {
            return $this->signedDevicePayload([
                'device_serial' => $device->serial_number,
                'mac_address' => 'AA:BB:CC:DD:EE:31',
                'device_id' => 'IRIGA-LOCAL-PC-01',
                'reader_id' => $device->reader_id,
                'timestamp' => now()->toIso8601String(),
                'nonce' => $nonce,
            ], 'POST', '/api/fingerprint-register-temp');
        };

        $authenticator = new BiometricDeviceAuthenticator();
        $localSuspensionRequest = Request::create('/api/biometric/suspensions', 'GET', [
            'device_serial' => $device->serial_number,
            'mac_address' => 'AA:BB:CC:DD:EE:31',
            'device_id' => 'IRIGA-LOCAL-PC-01',
            'reader_id' => $device->reader_id,
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'local-suspension-calendar-read',
        ], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $localSuspensionResult = $authenticator->authenticate($localSuspensionRequest);
        $this->assertSame($device->id, $localSuspensionResult['device']->id ?? null);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->getJson('/api/biometric/suspensions?' . http_build_query([
                'device_serial' => $device->serial_number,
                'mac_address' => 'AA:BB:CC:DD:EE:31',
                'device_id' => 'IRIGA-LOCAL-PC-01',
                'reader_id' => $device->reader_id,
                'timestamp' => now()->toIso8601String(),
                'nonce' => 'local-suspension-calendar-endpoint',
                'start_date' => now()->toDateString(),
                'end_date' => now()->addYear()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $unsignedPayload = $makePayload('local-enroll-unsigned');
        unset($unsignedPayload['signature']);
        $localUnsignedResult = $authenticator->authenticate(Request::create(
            '/api/fingerprint-register-temp',
            'POST',
            $unsignedPayload,
            [],
            [],
            ['REMOTE_ADDR' => '127.0.0.1']
        ));
        $this->assertSame(401, $localUnsignedResult['code'] ?? null);
        $this->assertSame(
            'Invalid device credential signature.',
            $localUnsignedResult['error'] ?? null
        );

        $localTimeClockPayload = $makePayload('local-time-clock-unsigned');
        unset($localTimeClockPayload['signature']);
        $localTimeClockRequest = Request::create(
            '/api/biometric/time-clock',
            'POST',
            $localTimeClockPayload + ['action' => 'TIME-IN'],
            [],
            [],
            ['REMOTE_ADDR' => '127.0.0.1']
        );
        $localTimeClockResult = $authenticator->authenticate($localTimeClockRequest);
        $this->assertSame(401, $localTimeClockResult['code'] ?? null);

        $signedLocalResult = $authenticator->authenticate(Request::create(
            '/api/fingerprint-register-temp',
            'POST',
            $makePayload('local-enroll-signed'),
            [],
            [],
            ['REMOTE_ADDR' => '127.0.0.1']
        ));
        $this->assertSame($device->id, $signedLocalResult['device']->id ?? null, $signedLocalResult['error'] ?? json_encode($signedLocalResult));

        $remoteUnsignedPayload = $makePayload('remote-enroll-unsigned');
        unset($remoteUnsignedPayload['signature']);
        $remoteResult = $authenticator->authenticate(Request::create(
            '/api/fingerprint-register-temp',
            'POST',
            $remoteUnsignedPayload,
            [],
            [],
            ['REMOTE_ADDR' => '192.168.1.25']
        ));
        $this->assertSame(401, $remoteResult['code'] ?? null);
        $this->assertSame('Invalid device credential signature.', $remoteResult['error'] ?? null);
    }

    public function test_enrolling_again_updates_the_existing_named_finger_slot(): void
    {
        $branch = Branch::create([
            'branch_code' => 'BR-FINGER-SLOTS',
            'branch_name' => 'Finger Slot Test Branch',
            'address' => 'Finger Slot Test Address',
        ]);
        $user = User::create([
            'name' => 'Finger Slot Employee',
            'email' => 'finger.slot.employee@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);
        $employee = EmployeeProfile::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'employee_number' => 'EMP-FINGER-SLOTS',
            'first_name' => 'Finger',
            'last_name' => 'Slot',
            'position' => 'Staff',
            'date_hired' => now(),
            'is_fingerprint_registered' => false,
        ]);
        $device = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-FF',
            'serial_number' => 'SERIAL-FINGER-SLOTS-001',
            'api_secret' => self::DEVICE_SECRET,
            'device_name' => 'Finger Slot Test Scanner',
            'device_type' => 'computer',
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);

        $registrations = [
            ['finger_name' => 'Left Thumb', 'fingerprint_data' => base64_encode(str_repeat('left-thumb-first', 10))],
            ['finger_name' => 'Right Thumb', 'fingerprint_data' => base64_encode(str_repeat('right-thumb', 10))],
            ['finger_name' => 'Left Thumb', 'fingerprint_data' => base64_encode(str_repeat('left-thumb-updated', 10))],
        ];
        $tamperedNamePayload = $this->signedDevicePayload([
            'employee_number' => $employee->employee_number,
            'fingerprint_data' => base64_encode(str_repeat('tampered-finger-name', 10)),
            'finger_name' => 'Left Thumb',
            'device_serial' => $device->serial_number,
            'wifi_mac' => 'AA:BB:CC:DD:EE:FF',
            'device_id' => (string) $device->id,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'finger-slot-tampered-name',
        ], 'POST', '/api/fingerprint-register-temp');
        $tamperedNamePayload['finger_name'] = 'Right Thumb';
        $tamperedNameResponse = (new BiometricController())->registerFingerprintTemp(
            Request::create('/api/fingerprint-register-temp', 'POST', $tamperedNamePayload)
        );
        $this->assertSame(401, $tamperedNameResponse->getStatusCode(), $tamperedNameResponse->content());

        foreach ($registrations as $index => $registration) {
            $payload = $this->signedDevicePayload([
                'employee_number' => $employee->employee_number,
                'fingerprint_data' => $registration['fingerprint_data'],
                'finger_name' => $registration['finger_name'],
                'device_serial' => $device->serial_number,
                'wifi_mac' => 'AA:BB:CC:DD:EE:FF',
                'device_id' => (string) $device->id,
                'mac_address' => 'AA:BB:CC:DD:EE:FF',
                'timestamp' => now()->toIso8601String(),
                'nonce' => 'finger-slot-enrollment-' . $index,
            ], 'POST', '/api/fingerprint-register-temp');

            $response = (new BiometricController())->registerFingerprintTemp(
                Request::create('/api/fingerprint-register-temp', 'POST', $payload)
            );

            $this->assertSame(200, $response->getStatusCode(), $response->content());
        }

        $savedTemplates = json_decode($employee->fresh()->fingerprint_template, true);
        $this->assertSame(2, count($savedTemplates['templates']));
        $this->assertEqualsCanonicalizing(
            ['Left Thumb', 'Right Thumb'],
            array_column($savedTemplates['templates'], 'finger_name')
        );
        $this->assertSame(
            2,
            cache()->get('fingerprint_temp_' . $employee->employee_number)['fingerprint_count']
        );
        $latestRegistration = cache()->get('fingerprint_temp_' . $employee->employee_number);
        $this->assertTrue($latestRegistration['database_saved']);
        $this->assertSame('updated', $latestRegistration['action']);
        $this->assertSame('Left Thumb', $latestRegistration['finger_name']);
        $this->assertSame($registrations[2]['fingerprint_data'], $latestRegistration['fingerprint_data']);

        $statusPayload = $this->signedDevicePayload([
            'employee_number' => $employee->employee_number,
            'device_serial' => $device->serial_number,
            'wifi_mac' => 'AA:BB:CC:DD:EE:FF',
            'device_id' => (string) $device->id,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'finger-slot-status-refresh',
        ], 'GET', '/api/biometric/templates');
        $statusResponse = (new BiometricController())->getFingerprintTemplates(
            Request::create('/api/biometric/templates', 'GET', $statusPayload)
        );
        $registeredFingers = collect(json_decode($statusResponse->content(), true)['templates'])
            ->flatMap(fn ($template) => $template['registered_fingers'] ?? [])
            ->unique()
            ->values()
            ->all();
        $this->assertEqualsCanonicalizing(['Left Thumb', 'Right Thumb'], $registeredFingers);

        $employee->update([
            'fingerprint_template' => json_encode([
                'version' => 2,
                'templates' => [
                    ['finger_name' => 'Right Thumb', 'fingerprint_data' => $registrations[1]['fingerprint_data']],
                ],
            ], JSON_UNESCAPED_SLASHES),
            'is_fingerprint_registered' => true,
        ]);
        $afterRemovalPayload = $this->signedDevicePayload([
            'employee_number' => $employee->employee_number,
            'device_serial' => $device->serial_number,
            'wifi_mac' => 'AA:BB:CC:DD:EE:FF',
            'device_id' => (string) $device->id,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'finger-slot-status-after-removal',
        ], 'GET', '/api/biometric/templates');
        $afterRemovalResponse = (new BiometricController())->getFingerprintTemplates(
            Request::create('/api/biometric/templates', 'GET', $afterRemovalPayload)
        );
        $remainingFingers = collect(json_decode($afterRemovalResponse->content(), true)['templates'])
            ->flatMap(fn ($template) => $template['registered_fingers'] ?? [])
            ->unique()
            ->values()
            ->all();
        $this->assertSame(['Right Thumb'], $remainingFingers);

        $employee->update([
            'fingerprint_template' => null,
            'is_fingerprint_registered' => false,
        ]);
        $emptyStatusPayload = $this->signedDevicePayload([
            'employee_number' => $employee->employee_number,
            'device_serial' => $device->serial_number,
            'wifi_mac' => 'AA:BB:CC:DD:EE:FF',
            'device_id' => (string) $device->id,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'finger-slot-status-cleared',
        ], 'GET', '/api/biometric/templates');
        $emptyStatusResponse = (new BiometricController())->getFingerprintTemplates(
            Request::create('/api/biometric/templates', 'GET', $emptyStatusPayload)
        );
        $this->assertSame([], json_decode($emptyStatusResponse->content(), true)['templates']);
    }

    public function test_device_templates_are_branch_scoped_and_cross_branch_clock_is_rejected(): void
    {
        $iriga = Branch::create([
            'branch_code' => 'BR-TEMPLATE-IRIGA',
            'branch_name' => 'Iriga Test Branch',
            'address' => 'Iriga Test Address',
        ]);
        $buhi = Branch::create([
            'branch_code' => 'BR-TEMPLATE-BUHI',
            'branch_name' => 'Buhi Test Branch',
            'address' => 'Buhi Test Address',
        ]);

        $irigaUser = User::create([
            'name' => 'Iriga Test Employee',
            'email' => 'iriga.template.employee@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
            'branch_id' => $iriga->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);
        $irigaEmployee = EmployeeProfile::create([
            'user_id' => $irigaUser->id,
            'branch_id' => $iriga->id,
            'employee_number' => 'EMP-IRIGA-TEMPLATE',
            'first_name' => 'Iriga',
            'last_name' => 'Employee',
            'position' => 'Staff',
            'date_hired' => now(),
            'fingerprint_template' => 'iriga-fingerprint-template',
            'is_fingerprint_registered' => true,
        ]);

        $buhiUser = User::create([
            'name' => 'Buhi Test Employee',
            'email' => 'buhi.template.employee@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
            'branch_id' => $buhi->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);
        $buhiEmployee = EmployeeProfile::create([
            'user_id' => $buhiUser->id,
            'branch_id' => $buhi->id,
            'employee_number' => 'EMP-BUHI-TEMPLATE',
            'first_name' => 'Buhi',
            'last_name' => 'Employee',
            'position' => 'Staff',
            'date_hired' => now(),
            'fingerprint_template' => 'buhi-fingerprint-template',
            'is_fingerprint_registered' => true,
        ]);

        $device = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-FF',
            'serial_number' => 'SERIAL-IRIGA-TEMPLATE-001',
            'api_secret' => self::DEVICE_SECRET,
            'device_name' => 'Iriga Test Scanner',
            'device_type' => 'computer',
            'branch_id' => $iriga->id,
            'status' => 'active',
        ]);

        $timestamp = now()->toIso8601String();
        $deviceIdentity = $this->signedDevicePayload([
            'employee_id' => null,
            'employee_number' => null,
            'device_serial' => $device->serial_number,
            'wifi_mac' => 'AA:BB:CC:DD:EE:FF',
            'device_id' => (string) $device->id,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'timestamp' => $timestamp,
            'nonce' => 'iriga-template-list-nonce',
        ], 'GET', '/api/biometric/templates');
        $templatesResponse = (new BiometricController())->getFingerprintTemplates(
            Request::create('/api/biometric/templates', 'GET', $deviceIdentity)
        );

        $this->assertSame(200, $templatesResponse->getStatusCode(), $templatesResponse->content());
        $templateNumbers = collect(json_decode($templatesResponse->content(), true)['templates'])
            ->pluck('employee_number')
            ->unique()
            ->values()
            ->all();
        $this->assertSame([$irigaEmployee->employee_number], $templateNumbers);

        $guestCheckIdentity = $this->signedDevicePayload([
            'device_serial' => $device->serial_number,
            'wifi_mac' => 'AA:BB:CC:DD:EE:FF',
            'device_id' => (string) $device->id,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'iriga-guest-cross-branch-check',
            'fingerprint_scope' => 'guest-check',
        ], 'GET', '/api/biometric/templates');
        $guestCheckResponse = (new BiometricController())->getFingerprintTemplates(
            Request::create('/api/biometric/templates', 'GET', $guestCheckIdentity)
        );
        $this->assertSame(200, $guestCheckResponse->getStatusCode(), $guestCheckResponse->content());
        $guestTemplateNumbers = collect(json_decode($guestCheckResponse->content(), true)['templates'])
            ->pluck('employee_number')
            ->unique()
            ->values()
            ->all();
        $this->assertSame([$irigaEmployee->employee_number], $guestTemplateNumbers);

        $duplicateCheckIdentity = $this->signedDevicePayload([
            'employee_id' => null,
            'employee_number' => $irigaEmployee->employee_number,
            'device_serial' => $device->serial_number,
            'wifi_mac' => 'AA:BB:CC:DD:EE:FF',
            'device_id' => (string) $device->id,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'iriga-cross-branch-duplicate-check',
            'fingerprint_scope' => 'duplicate-check',
        ], 'GET', '/api/biometric/templates');
        $duplicateCheckResponse = (new BiometricController())->getFingerprintTemplates(
            Request::create('/api/biometric/templates', 'GET', $duplicateCheckIdentity)
        );
        $this->assertSame(200, $duplicateCheckResponse->getStatusCode(), $duplicateCheckResponse->content());
        $duplicateCheckNumbers = collect(json_decode($duplicateCheckResponse->content(), true)['templates'])
            ->pluck('employee_number')
            ->unique()
            ->values()
            ->all();
        $this->assertEqualsCanonicalizing([
            $irigaEmployee->employee_number,
            $buhiEmployee->employee_number,
        ], $duplicateCheckNumbers);
        $duplicateCheckTemplate = json_decode($duplicateCheckResponse->content(), true)['templates'][0];
        $this->assertEqualsCanonicalizing([
            'employee_number',
            'first_name',
            'last_name',
            'branch_name',
            'fingerprint_template',
        ], array_keys($duplicateCheckTemplate));

        $duplicateEnrollment = $this->signedDevicePayload([
            'employee_number' => $irigaEmployee->employee_number,
            'fingerprint_data' => base64_encode('buhi-fingerprint-template'),
            'finger_name' => 'Left Thumb',
            'device_serial' => $device->serial_number,
            'wifi_mac' => 'AA:BB:CC:DD:EE:FF',
            'device_id' => (string) $device->id,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'iriga-duplicate-buhi-fingerprint',
        ], 'POST', '/api/fingerprint-register-temp');
        $duplicateEnrollmentResponse = (new BiometricController())->registerFingerprintTemp(
            Request::create('/api/fingerprint-register-temp', 'POST', $duplicateEnrollment)
        );
        $this->assertSame(409, $duplicateEnrollmentResponse->getStatusCode(), $duplicateEnrollmentResponse->content());
        $this->assertStringContainsString(
            $buhiEmployee->employee_number,
            json_decode($duplicateEnrollmentResponse->content(), true)['message']
        );

        $tamperedScope = $this->signedDevicePayload([
            'device_serial' => $device->serial_number,
            'wifi_mac' => 'AA:BB:CC:DD:EE:FF',
            'device_id' => (string) $device->id,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'tampered-cross-branch-scope',
            'fingerprint_scope' => 'duplicate-check',
        ], 'GET', '/api/biometric/templates');
        $tamperedScope['fingerprint_scope'] = 'all';
        $tamperedScopeResponse = (new BiometricController())->getFingerprintTemplates(
            Request::create('/api/biometric/templates', 'GET', $tamperedScope)
        );
        $this->assertSame(401, $tamperedScopeResponse->getStatusCode(), $tamperedScopeResponse->content());

        $replayResponse = (new BiometricController())->getFingerprintTemplates(
            Request::create('/api/biometric/templates', 'GET', $deviceIdentity)
        );
        $this->assertSame(401, $replayResponse->getStatusCode(), $replayResponse->content());

        $attendancePayload = $this->signedDevicePayload([
            'employee_id' => null,
            'employee_number' => $buhiEmployee->employee_number,
            'fingerprint_data' => $buhiEmployee->fingerprint_template,
            'action' => 'TIME-IN',
            'device_serial' => $device->serial_number,
            'wifi_mac' => 'AA:BB:CC:DD:EE:FF',
            'device_id' => (string) $device->id,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'timestamp' => $timestamp,
            'attendance_timestamp' => $timestamp,
            'nonce' => 'cross-branch-time-in-nonce',
        ], 'POST', '/api/biometric/time-clock');
        $attendanceResponse = (new BiometricController())->processTimeClock(
            Request::create('/api/biometric/time-clock', 'POST', $attendancePayload)
        );

        $this->assertSame(401, $attendanceResponse->getStatusCode(), $attendanceResponse->content());
        $this->assertSame('Fingerprint not recognized', json_decode($attendanceResponse->content(), true)['error']);
        $this->assertDatabaseMissing('attendance_logs', [
            'employee_profile_id' => $buhiEmployee->id,
        ]);

        $buhiDevice = Device::create([
            'mac_address' => '11-22-33-44-55-66',
            'serial_number' => 'SERIAL-BUHI-TEMPLATE-001',
            'api_secret' => self::DEVICE_SECRET,
            'device_name' => 'Buhi Test Scanner',
            'device_type' => 'computer',
            'branch_id' => $buhi->id,
            'status' => 'active',
        ]);
        $reverseAttendancePayload = $this->signedDevicePayload([
            'employee_number' => $irigaEmployee->employee_number,
            'fingerprint_data' => $irigaEmployee->fingerprint_template,
            'action' => 'TIME-IN',
            'device_serial' => $buhiDevice->serial_number,
            'wifi_mac' => '11:22:33:44:55:66',
            'device_id' => (string) $buhiDevice->id,
            'mac_address' => '11:22:33:44:55:66',
            'timestamp' => now()->toIso8601String(),
            'attendance_timestamp' => now()->toIso8601String(),
            'nonce' => 'cross-branch-time-in-reverse',
        ], 'POST', '/api/biometric/time-clock');
        $reverseAttendanceResponse = (new BiometricController())->processTimeClock(
            Request::create('/api/biometric/time-clock', 'POST', $reverseAttendancePayload)
        );

        $this->assertSame(401, $reverseAttendanceResponse->getStatusCode(), $reverseAttendanceResponse->content());
        $this->assertSame('Fingerprint not recognized', json_decode($reverseAttendanceResponse->content(), true)['error']);
        $this->assertDatabaseMissing('attendance_logs', [
            'employee_profile_id' => $irigaEmployee->id,
        ]);
    }

    public function test_templates_endpoint_returns_service_unavailable_when_device_credential_cannot_be_decrypted(): void
    {
        $branch = Branch::create([
            'branch_code' => 'BR-UNREADABLE-CREDENTIAL',
            'branch_name' => 'Unreadable Credential Branch',
            'address' => 'Unreadable Credential Address',
        ]);
        $device = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-71',
            'serial_number' => 'SERIAL-UNREADABLE-CREDENTIAL',
            'api_secret' => self::DEVICE_SECRET,
            'device_name' => 'Unreadable Credential Scanner',
            'device_type' => 'computer',
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        \Illuminate\Support\Facades\DB::table('devices')
            ->where('id', $device->id)
            ->update(['api_secret' => 'not-a-valid-encrypted-credential']);

        $payload = $this->signedDevicePayload([
            'device_serial' => $device->serial_number,
            'wifi_mac' => 'AA:BB:CC:DD:EE:71',
            'device_id' => (string) $device->id,
            'mac_address' => 'AA:BB:CC:DD:EE:71',
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'unreadable-device-credential',
            'fingerprint_scope' => 'duplicate-check',
        ], 'GET', '/api/biometric/templates');
        $response = (new BiometricController())->getFingerprintTemplates(
            Request::create('/api/biometric/templates', 'GET', $payload)
        );

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame(
            'The server cannot decrypt this device credential. Restore the original APP_KEY or re-provision this device credential.',
            json_decode($response->content(), true)['error']
        );
    }

    public function test_time_out_requires_time_in_and_a_valid_pair_is_reflected_in_dtr(): void
    {
        $this->travelTo(now()->nextWeekday()->startOfDay()->setTime(7, 0));

        $branch = Branch::create([
            'branch_code' => 'BR-DTR-CLOCK-PAIR',
            'branch_name' => 'DTR Clock Pair Branch',
            'address' => 'DTR Clock Pair Address',
        ]);
        $user = User::create([
            'name' => 'DTR Clock Pair Employee',
            'email' => 'dtr.clock.pair@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);
        $employee = EmployeeProfile::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'employee_number' => 'EMP-DTR-CLOCK-PAIR',
            'first_name' => 'DTR Clock Pair',
            'last_name' => 'Employee',
            'position' => 'Staff',
            'date_hired' => now()->toDateString(),
            'fingerprint_template' => 'dtr-clock-pair-template',
            'is_fingerprint_registered' => true,
        ]);
        $device = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-FF',
            'serial_number' => 'SERIAL-DTR-CLOCK-PAIR-001',
            'api_secret' => self::DEVICE_SECRET,
            'device_name' => 'DTR Clock Pair Scanner',
            'device_type' => 'computer',
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);

        $createPayload = function (string $action, \Carbon\Carbon $attendanceTime, string $nonce) use ($device, $employee): array {
            return $this->signedDevicePayload([
                'employee_id' => null,
                'employee_number' => $employee->employee_number,
                'fingerprint_data' => $employee->fingerprint_template,
                'action' => $action,
                'device_serial' => $device->serial_number,
                'wifi_mac' => 'AA:BB:CC:DD:EE:FF',
                'device_id' => (string) $device->id,
                'mac_address' => 'AA:BB:CC:DD:EE:FF',
                'timestamp' => now()->toIso8601String(),
                'attendance_timestamp' => $attendanceTime->toIso8601String(),
                'nonce' => $nonce,
            ], 'POST', '/api/biometric/time-clock');
        };

        $clockOutWithoutClockIn = (new BiometricController())->processTimeClock(
            Request::create('/api/biometric/time-clock', 'POST', $createPayload(
                'TIME-OUT',
                now(),
                'dtr-clock-pair-timeout-without-in'
            ))
        );

        $this->assertSame(409, $clockOutWithoutClockIn->getStatusCode(), $clockOutWithoutClockIn->content());
        $this->assertDatabaseMissing('attendance_logs', [
            'employee_profile_id' => $employee->id,
            'attendance_date' => now()->toDateString(),
        ]);

        $clockIn = (new BiometricController())->processTimeClock(
            Request::create('/api/biometric/time-clock', 'POST', $createPayload(
                'TIME-IN',
                now(),
                'dtr-clock-pair-timein'
            ))
        );
        $this->assertSame(200, $clockIn->getStatusCode(), $clockIn->content());

        $this->travelTo(now()->setTime(16, 0));
        $clockOut = (new BiometricController())->processTimeClock(
            Request::create('/api/biometric/time-clock', 'POST', $createPayload(
                'TIME-OUT',
                now(),
                'dtr-clock-pair-timeout'
            ))
        );
        $this->assertSame(200, $clockOut->getStatusCode(), $clockOut->content());

        $dtr = \App\Models\DTR::where('employee_profile_id', $employee->id)->firstOrFail();
        $breakdown = $dtr->getCalculationBreakdown();
        $this->assertSame(1, $breakdown['days_present']);
        $expectedAbsences = app(\App\Services\WorkingDayService::class)
            ->countWorkingDays($dtr->period_start, now()->subDay(), $branch->id);
        $this->assertSame($expectedAbsences, $breakdown['days_absent']);
        $this->assertSame(8.0, $breakdown['total_hours']);
    }

    public function test_time_clock_rejects_stale_offline_attendance(): void
    {
        $this->travelTo('2026-10-28 08:00:00');
        $branch = Branch::create([
            'branch_code' => 'BR-OFFLINE-DENIED',
            'branch_name' => 'Offline Denied Branch',
            'address' => 'Offline Denied Address',
        ]);
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $employee = EmployeeProfile::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'employee_number' => 'EMP-OFFLINE-DENIED',
            'first_name' => 'Offline',
            'last_name' => 'Denied',
            'position' => 'Staff',
            'date_hired' => now()->toDateString(),
            'fingerprint_template' => 'offline-denied-template',
            'is_fingerprint_registered' => true,
        ]);
        $device = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-43',
            'serial_number' => 'SERIAL-OFFLINE-DENIED',
            'api_secret' => self::DEVICE_SECRET,
            'device_name' => 'Offline Denied Scanner',
            'device_type' => 'computer',
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $payload = $this->signedDevicePayload([
            'employee_number' => $employee->employee_number,
            'fingerprint_data' => $employee->fingerprint_template,
            'action' => 'TIME-IN',
            'device_serial' => $device->serial_number,
            'wifi_mac' => $device->mac_address,
            'device_id' => (string) $device->id,
            'mac_address' => $device->mac_address,
            'timestamp' => now()->toIso8601String(),
            'attendance_timestamp' => now()->subMinutes(2)->toIso8601String(),
            'nonce' => 'stale-offline-attendance',
        ], 'POST', '/api/biometric/time-clock');

        $response = (new BiometricController())->processTimeClock(
            Request::create('/api/biometric/time-clock', 'POST', $payload)
        );

        $this->assertSame(409, $response->getStatusCode(), $response->content());
        $this->assertStringContainsString('Offline attendance is disabled', json_decode($response->content(), true)['error']);
        $this->assertDatabaseMissing('attendance_logs', ['employee_profile_id' => $employee->id]);
        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_legacy_browser_attendance_requires_a_signed_registered_device(): void
    {
        $branch = Branch::create([
            'branch_code' => 'BR-LAPTOP-001',
            'branch_name' => 'Laptop Branch',
            'address' => 'Laptop street',
        ]);

        $user = User::create([
            'name' => 'Laptop Test Employee',
            'email' => 'laptop.employee@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);

        $employeeProfile = EmployeeProfile::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'employee_number' => 'EMP-LAPTOP-001',
            'first_name' => 'Laptop',
            'last_name' => 'Employee',
            'position' => 'Staff',
            'basic_salary' => 20000,
            'date_hired' => now(),
            'fingerprint_template' => 'sample-fingerprint-template',
            'is_fingerprint_registered' => true,
        ]);

        $device = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-FF',
            'wifi_mac_address' => null,
            'laptop_mac_address' => 'AA-BB-CC-DD-EE-11',
            'serial_number' => 'SERIAL-LAPTOP-001',
            'reader_id' => 'reader-laptop-001',
            'api_secret' => self::DEVICE_SECRET,
            'device_name' => 'Laptop Terminal',
            'device_type' => 'computer',
            'branch_id' => $branch->id,
            'location' => 'Ground Floor',
            'status' => 'active',
        ]);

        $invalidPayload = [
            'fingerprint_data' => 'sample-fingerprint-template',
            'employee_number' => 'EMP-LAPTOP-001',
            'laptop_mac' => 'AA-BB-CC-DD-EE-11',
            'mac_address' => 'AA:BB:CC:DD:EE:11',
            'timestamp' => now()->format('m/d/Y h:i:s A'),
            'nonce' => 'invalid-device-test',
        ];
        $invalidPayload['signature'] = $this->legacySignature($invalidPayload);
        $this->actingAs($user)->postJson('/api/biometric/attendance', $invalidPayload)
            ->assertUnprocessable();

        $stalePayload = $this->signedDevicePayload([
            'employee_number' => $employeeProfile->employee_number,
            'fingerprint_data' => $employeeProfile->fingerprint_template,
            'device_serial' => $device->serial_number,
            'reader_id' => $device->reader_id,
            'wifi_mac' => 'AA:BB:CC:DD:EE:FF',
            'device_id' => $device->laptop_mac_address,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'timestamp' => now()->subMinutes(2)->toIso8601String(),
            'nonce' => 'stale-legacy-attendance',
        ], 'POST', '/api/biometric/attendance');
        $this->actingAs($user)->postJson('/api/biometric/attendance', $stalePayload)
            ->assertStatus(409)
            ->assertJson(['attendance_blocked' => true]);

        $this->assertDatabaseMissing('attendance_logs', [
            'employee_profile_id' => $employeeProfile->id,
        ]);
    }

    public function test_scanner_attendance_is_rejected_during_an_approved_suspension(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-09-28 08:00:00');

        $branch = Branch::create([
            'branch_code' => 'BR-SUSPENSION-BLOCK',
            'branch_name' => 'Suspension Block Branch',
            'address' => 'Suspension Test Address',
        ]);

        $user = User::create([
            'name' => 'Suspension Test Employee',
            'email' => 'suspension.block@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);

        $employee = EmployeeProfile::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'employee_number' => 'EMP-SUSPENSION-BLOCK',
            'first_name' => 'Suspension',
            'last_name' => 'Test Employee',
            'position' => 'Staff',
            'basic_salary' => 20000,
            'date_hired' => now(),
            'fingerprint_template' => 'suspension-test-template',
            'is_fingerprint_registered' => true,
        ]);

        $device = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-FF',
            'serial_number' => 'SERIAL-SUSPENSION-001',
            'api_secret' => self::DEVICE_SECRET,
            'device_name' => 'Suspension Test Terminal',
            'device_type' => 'computer',
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);

        CalendarEvent::create([
            'title' => 'Approved Suspension',
            'event_date' => '2026-09-28',
            'event_type' => 'suspension',
            'created_by' => $user->id,
            'branch_id' => null,
            'approval_status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $suspensionQuery = $this->signedDevicePayload([
            'device_serial' => $device->serial_number,
            'wifi_mac' => 'AA:BB:CC:DD:EE:FF',
            'device_id' => (string) $device->id,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'suspension-query-nonce',
            'start_date' => '2026-09-28',
            'end_date' => '2026-09-28',
        ], 'GET', '/api/biometric/suspensions');
        $suspensionsResponse = (new BiometricController())->getAttendanceSuspensions(
            Request::create('/api/biometric/suspensions', 'GET', $suspensionQuery)
        );

        $this->assertSame(200, $suspensionsResponse->getStatusCode());
        $suspensionDates = json_decode($suspensionsResponse->content(), true)['dates'];
        $this->assertContains('2026-09-28', $suspensionDates, json_encode($suspensionDates));

        $payload = $this->signedDevicePayload([
            'employee_id' => null,
            'employee_number' => $employee->employee_number,
            'fingerprint_data' => 'suspension-test-template',
            'action' => 'TIME-IN',
            'device_serial' => $device->serial_number,
            'device_id' => (string) $device->id,
            'wifi_mac' => 'AA:BB:CC:DD:EE:FF',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'timestamp' => now()->toIso8601String(),
            'attendance_timestamp' => now()->toIso8601String(),
            'nonce' => 'suspension-test-nonce',
        ], 'POST', '/api/biometric/time-clock');

        $response = (new BiometricController())->processTimeClock(
            Request::create('/api/biometric/time-clock', 'POST', $payload)
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertTrue(json_decode($response->content(), true)['attendance_blocked']);
        $this->assertDatabaseMissing('attendance_logs', [
            'employee_profile_id' => $employee->id,
            'attendance_date' => '2026-09-28',
        ]);

        AttendanceLog::create([
            'employee_profile_id' => $employee->id,
            'employee_id' => $user->id,
            'branch_id' => $branch->id,
            'attendance_date' => '2026-09-28',
            'am_in' => '2026-09-28 08:00:00',
            'status' => 'present',
        ]);
        $this->assertNotNull(AttendanceLog::where('employee_profile_id', $employee->id)
            ->whereDate('attendance_date', '2026-09-28')
            ->first()?->am_in);
        \Illuminate\Support\Carbon::setTestNow('2026-09-28 09:15:00');
        $payload['action'] = 'TIME-OUT';
        $payload['timestamp'] = '2026-09-28T09:15:00+08:00';
        $payload['attendance_timestamp'] = $payload['timestamp'];
        $payload['nonce'] = 'suspension-timeout-nonce';
        $payload = $this->signedDevicePayload($payload, 'POST', '/api/biometric/time-clock');

        $timeoutResponse = (new BiometricController())->processTimeClock(
            Request::create('/api/biometric/time-clock', 'POST', $payload)
        );

        $this->assertSame(200, $timeoutResponse->getStatusCode(), $timeoutResponse->content());
        $attendanceAfterTimeout = AttendanceLog::where('employee_profile_id', $employee->id)
            ->whereDate('attendance_date', '2026-09-28')
            ->first();
        $this->assertNotNull($attendanceAfterTimeout);
        $this->assertSame('09:15:00', $attendanceAfterTimeout->pm_out->format('H:i:s'));

        $clockOutPayload = $this->signedDevicePayload([
            'branch_id' => $branch->id,
            'employee_number' => $employee->employee_number,
            'attendance_type' => 'am_out',
            'fingerprint_data' => 'suspension-test-template',
            'device_serial' => $device->serial_number,
            'device_id' => (string) $device->id,
            'wifi_mac' => 'AA:BB:CC:DD:EE:FF',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'suspension-legacy-clock-out',
        ], 'POST', '/api/attendance/clock');
        $clockOutResponse = (new AttendanceController())->clockAttendance(
            Request::create('/api/attendance/clock', 'POST', $clockOutPayload)
        );

        $this->assertSame(200, $clockOutResponse->getStatusCode(), $clockOutResponse->getContent());

        $this->actingAs($user)->postJson('/api/biometric/process', [
            'fingerprint_data' => 'suspension-test-template',
            'timestamp' => now()->format('m/d/Y h:i:s A'),
        ])->assertUnprocessable();

        $clockPayload = $this->signedDevicePayload([
            'branch_id' => $branch->id,
            'employee_number' => $employee->employee_number,
            'attendance_type' => 'am_in',
            'fingerprint_data' => 'suspension-test-template',
            'device_serial' => $device->serial_number,
            'device_id' => (string) $device->id,
            'wifi_mac' => 'AA:BB:CC:DD:EE:FF',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'suspension-legacy-clock-in',
        ], 'POST', '/api/attendance/clock');
        $clockResponse = (new AttendanceController())->clockAttendance(
            Request::create('/api/attendance/clock', 'POST', $clockPayload)
        );

        $this->assertSame(403, $clockResponse->getStatusCode());
        $this->assertTrue(json_decode($clockResponse->content(), true)['attendance_blocked']);
        $this->assertSame(1, AttendanceLog::where('employee_profile_id', $employee->id)
            ->whereDate('attendance_date', '2026-09-28')
            ->count());

        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_attendance_endpoints_block_punches_during_approved_leave(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-09-29 08:00:00');

        $branch = Branch::create([
            'branch_code' => 'BR-APPROVED-LEAVE-BLOCK',
            'branch_name' => 'Approved Leave Block Branch',
            'address' => 'Approved Leave Test Address',
        ]);
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $employee = EmployeeProfile::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'employee_number' => 'EMP-APPROVED-LEAVE-BLOCK',
            'first_name' => 'Leave',
            'last_name' => 'Test Employee',
            'position' => 'Staff',
            'basic_salary' => 20000,
            'date_hired' => '2020-01-01',
            'fingerprint_template' => 'approved-leave-test-template',
            'is_fingerprint_registered' => true,
        ]);
        LeaveRequest::create([
            'employee_id' => $user->id,
            'employee_profile_id' => $employee->id,
            'leave_type' => 'vacation',
            'start_date' => '2026-09-29',
            'end_date' => '2026-09-29',
            'total_days' => 1,
            'reason' => 'Approved leave attendance blocking test',
            'status' => 'approved',
            'is_absent' => false,
        ]);

        $this->actingAs($user)->postJson('/api/biometric/process', [
            'fingerprint_data' => 'approved-leave-test-template',
            'timestamp' => now()->format('m/d/Y h:i:s A'),
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('attendance_logs', [
            'employee_profile_id' => $employee->id,
            'attendance_date' => '2026-09-29',
        ]);

        $device = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-72',
            'laptop_mac_address' => 'LEAVE-BLOCK-DEVICE',
            'serial_number' => 'SERIAL-LEAVE-BLOCK-001',
            'reader_id' => '70315bbe-3635-8b41-b4af-04a0bcd6e941',
            'api_secret' => self::DEVICE_SECRET,
            'device_name' => 'Approved Leave Test Scanner',
            'device_type' => 'computer',
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $timeClockPayload = $this->signedDevicePayload([
            'employee_number' => $employee->employee_number,
            'fingerprint_data' => 'approved-leave-test-template',
            'action' => 'TIME-IN',
            'device_serial' => $device->serial_number,
            'reader_id' => $device->reader_id,
            'wifi_mac' => $device->mac_address,
            'device_id' => $device->laptop_mac_address,
            'mac_address' => $device->mac_address,
            'timestamp' => now()->toIso8601String(),
            'attendance_timestamp' => now()->toIso8601String(),
            'nonce' => 'approved-leave-time-clock',
        ], 'POST', '/api/biometric/time-clock');
        $timeClockResponse = (new BiometricController())->processTimeClock(
            Request::create('/api/biometric/time-clock', 'POST', $timeClockPayload)
        );

        $this->assertSame(403, $timeClockResponse->getStatusCode(), $timeClockResponse->getContent());
        $this->assertTrue(json_decode($timeClockResponse->getContent(), true)['attendance_blocked']);
        $this->assertDatabaseMissing('attendance_logs', [
            'employee_profile_id' => $employee->id,
            'attendance_date' => '2026-09-29',
        ]);

        $clockPayload = $this->signedDevicePayload([
            'branch_id' => $branch->id,
            'employee_number' => $employee->employee_number,
            'attendance_type' => 'am_in',
            'fingerprint_data' => 'approved-leave-test-template',
            'device_serial' => $device->serial_number,
            'reader_id' => $device->reader_id,
            'device_id' => $device->laptop_mac_address,
            'mac_address' => $device->mac_address,
            'wifi_mac' => $device->mac_address,
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'approved-leave-attendance-clock',
        ], 'POST', '/api/attendance/clock');
        $clockResponse = (new AttendanceController())->clockAttendance(
            Request::create('/api/attendance/clock', 'POST', $clockPayload)
        );

        $this->assertSame(403, $clockResponse->getStatusCode(), $clockResponse->getContent());
        $this->assertTrue(json_decode($clockResponse->getContent(), true)['attendance_blocked']);
        $this->assertDatabaseMissing('attendance_logs', [
            'employee_profile_id' => $employee->id,
            'attendance_date' => '2026-09-29',
        ]);

        $adminFingerprint = 'approved-leave-admin-template';
        AdminProfile::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'employee_profile_id' => $employee->id,
            'employee_number' => 'ADM-APPROVED-LEAVE-BLOCK',
            'first_name' => 'Admin',
            'last_name' => 'Leave Test',
            'fingerprint_template' => $adminFingerprint,
            'is_fingerprint_registered' => true,
        ]);
        $adminPayload = $this->signedDevicePayload([
            'employee_number' => 'ADM-APPROVED-LEAVE-BLOCK',
            'fingerprint_data' => $adminFingerprint,
            'device_serial' => $device->serial_number,
            'reader_id' => $device->reader_id,
            'wifi_mac' => $device->mac_address,
            'device_id' => $device->laptop_mac_address,
            'mac_address' => $device->mac_address,
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'approved-leave-admin-attendance',
        ], 'POST', '/api/biometric/admin-attendance');
        $staleAdminPayload = $this->signedDevicePayload(array_merge($adminPayload, [
            'timestamp' => now()->subMinutes(2)->toIso8601String(),
            'nonce' => 'stale-admin-attendance',
        ]), 'POST', '/api/biometric/admin-attendance');
        $staleAdminResponse = (new BiometricController())->processAdminAttendance(
            Request::create('/api/biometric/admin-attendance', 'POST', $staleAdminPayload)
        );
        $this->assertSame(409, $staleAdminResponse->getStatusCode(), $staleAdminResponse->getContent());

        $adminResponse = (new BiometricController())->processAdminAttendance(
            Request::create('/api/biometric/admin-attendance', 'POST', $adminPayload)
        );
        $this->assertSame(403, $adminResponse->getStatusCode(), $adminResponse->getContent());
        $this->assertTrue(json_decode($adminResponse->getContent(), true)['attendance_blocked']);

        $financeFingerprint = 'approved-leave-finance-template';
        FinanceProfile::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'employee_profile_id' => $employee->id,
            'employee_number' => 'FIN-APPROVED-LEAVE-BLOCK',
            'first_name' => 'Finance',
            'last_name' => 'Leave Test',
            'fingerprint_template' => $financeFingerprint,
            'is_fingerprint_registered' => true,
        ]);
        $financePayload = $this->signedDevicePayload([
            'employee_number' => 'FIN-APPROVED-LEAVE-BLOCK',
            'fingerprint_data' => $financeFingerprint,
            'device_serial' => $device->serial_number,
            'reader_id' => $device->reader_id,
            'wifi_mac' => $device->mac_address,
            'device_id' => $device->laptop_mac_address,
            'mac_address' => $device->mac_address,
            'timestamp' => now()->toIso8601String(),
            'nonce' => 'approved-leave-finance-attendance',
        ], 'POST', '/api/biometric/finance-attendance');
        $staleFinancePayload = $this->signedDevicePayload(array_merge($financePayload, [
            'timestamp' => now()->subMinutes(2)->toIso8601String(),
            'nonce' => 'stale-finance-attendance',
        ]), 'POST', '/api/biometric/finance-attendance');
        $staleFinanceResponse = (new BiometricController())->processFinanceAttendance(
            Request::create('/api/biometric/finance-attendance', 'POST', $staleFinancePayload)
        );
        $this->assertSame(409, $staleFinanceResponse->getStatusCode(), $staleFinanceResponse->getContent());

        $financeResponse = (new BiometricController())->processFinanceAttendance(
            Request::create('/api/biometric/finance-attendance', 'POST', $financePayload)
        );
        $this->assertSame(403, $financeResponse->getStatusCode(), $financeResponse->getContent());
        $this->assertTrue(json_decode($financeResponse->getContent(), true)['attendance_blocked']);
        $this->assertDatabaseMissing('attendance_logs', [
            'employee_profile_id' => $employee->id,
            'attendance_date' => '2026-09-29',
        ]);

        $otherBranch = Branch::create([
            'branch_code' => 'BR-LEAVE-BLOCK-SECOND',
            'branch_name' => 'Second Leave Block Branch',
            'address' => 'Second Leave Test Address',
        ]);
        $otherUser = User::factory()->create(['branch_id' => $otherBranch->id]);
        $otherEmployee = EmployeeProfile::create([
            'user_id' => $otherUser->id,
            'branch_id' => $otherBranch->id,
            'employee_number' => 'EMP-LEAVE-BLOCK-SECOND',
            'first_name' => 'Second',
            'last_name' => 'Branch Employee',
            'position' => 'Staff',
            'basic_salary' => 20000,
            'date_hired' => '2020-01-01',
            'fingerprint_template' => 'second-branch-leave-template',
            'is_fingerprint_registered' => true,
        ]);
        LeaveRequest::create([
            'employee_id' => $otherUser->id,
            'employee_profile_id' => $otherEmployee->id,
            'leave_type' => 'vacation',
            'start_date' => '2026-09-29',
            'end_date' => '2026-09-29',
            'total_days' => 1,
            'reason' => 'Pending leave attendance blocking test',
            'status' => 'pending',
            'is_absent' => false,
        ]);
        $this->assertNotSame($branch->id, $otherBranch->id);
        $this->assertNotNull(app(\App\Services\ApprovedLeaveAttendanceGuard::class)
            ->blockedReason($otherEmployee, '2026-09-29'));

        \Illuminate\Support\Carbon::setTestNow();
    }
}
