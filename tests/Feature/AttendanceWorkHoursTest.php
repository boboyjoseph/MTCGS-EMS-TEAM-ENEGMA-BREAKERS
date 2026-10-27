<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\BiometricController;
use App\Models\Branch;
use App\Models\Device;
use App\Models\EmployeeProfile;
use App\Models\SiteSetting;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceWorkHours;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AttendanceWorkHoursTest extends TestCase
{
    use RefreshDatabase;

    private const DEVICE_SECRET = 'test-work-hours-device-secret';

    public function test_hr_can_change_school_wide_default_work_hours(): void
    {
        $hr = User::factory()->create([
            'role' => 'admin',
            'admin_type' => 'hr',
        ]);

        $this->actingAs($hr)
            ->get(route('admin.shifts.index'))
            ->assertOk()
            ->assertSee('School-Wide Default Work Hours')
            ->assertSee('/admin/shifts/default-work-hours');

        $this->put(route('admin.shifts.default-work-hours.update'), [
            'start_time' => '07:00',
            'end_time' => '16:00',
        ])->assertRedirect();

        $this->assertSame('07:00:00', SiteSetting::getValue(AttendanceWorkHours::START_TIME_SETTING));
        $this->assertSame('16:00:00', SiteSetting::getValue(AttendanceWorkHours::END_TIME_SETTING));
        $this->assertSame(
            '07:00',
            AttendanceWorkHours::scheduledStart(null, Carbon::parse('2026-10-28'))->format('H:i')
        );
        $this->assertSame(
            '16:00',
            AttendanceWorkHours::scheduledEnd(null, Carbon::parse('2026-10-28'))->format('H:i')
        );
    }

    public function test_assigned_employee_shift_takes_priority_over_hr_default(): void
    {
        $branch = $this->createBranch();
        $user = User::factory()->create(['role' => 'employee', 'branch_id' => $branch->id]);
        $shift = Shift::create([
            'name' => 'Assigned Shift',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'working_days' => 'Mon,Tue,Wed,Thu,Fri',
            'is_active' => true,
        ]);
        $profile = EmployeeProfile::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'shift_id' => $shift->id,
            'employee_number' => 'EMP-WORK-HOURS',
            'first_name' => 'Shift',
            'last_name' => 'Assigned',
            'position' => 'Staff',
            'date_hired' => today(),
        ]);
        SiteSetting::setValue(AttendanceWorkHours::START_TIME_SETTING, '07:00:00');
        SiteSetting::setValue(AttendanceWorkHours::END_TIME_SETTING, '16:00:00');

        $this->assertSame(
            '08:00',
            AttendanceWorkHours::scheduledStart($profile, Carbon::parse('2026-10-28'))->format('H:i')
        );
        $this->assertSame(
            '17:00',
            AttendanceWorkHours::scheduledEnd($profile, Carbon::parse('2026-10-28'))->format('H:i')
        );
    }

    public function test_non_hr_cannot_change_school_wide_default_work_hours(): void
    {
        $branch = $this->createBranch();
        $branchAdmin = User::factory()->create([
            'role' => 'admin',
            'admin_type' => 'branch_admin',
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($branchAdmin)
            ->put(route('admin.shifts.default-work-hours.update'), [
                'start_time' => '07:00',
                'end_time' => '16:00',
            ])
            ->assertForbidden();
    }

    public function test_hr_can_change_a_configured_shift_for_employees_assigned_to_it(): void
    {
        $hr = User::factory()->create([
            'role' => 'admin',
            'admin_type' => 'hr',
        ]);
        $shift = Shift::create([
            'name' => 'Staff Morning',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'working_days' => 'Mon,Tue,Wed,Thu,Fri',
            'is_active' => true,
        ]);

        $this->actingAs($hr)
            ->put(route('admin.shifts.update', $shift), [
                'name' => $shift->name,
                'start_time' => '07:00',
                'end_time' => '16:00',
                'working_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'start_time' => '07:00',
            'end_time' => '16:00',
        ]);
    }

    public function test_attendance_device_uses_hr_default_to_calculate_late_status(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 07:15:00', 'Asia/Manila'));
        $branch = $this->createBranch();
        $user = User::factory()->create([
            'role' => 'employee',
            'branch_id' => $branch->id,
        ]);
        $employee = EmployeeProfile::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'employee_number' => 'EMP-DYNAMIC-HOURS',
            'first_name' => 'Dynamic',
            'last_name' => 'Hours',
            'position' => 'Staff',
            'date_hired' => today(),
            'fingerprint_template' => base64_encode(str_repeat('work-hours-template', 10)),
            'is_fingerprint_registered' => true,
        ]);
        $device = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-71',
            'serial_number' => 'WORK-HOURS-SCANNER-001',
            'reader_id' => '70315bbe-3635-8b41-b4af-04a0bcd6e941',
            'api_secret' => self::DEVICE_SECRET,
            'device_name' => 'Work Hours Scanner',
            'device_type' => 'computer',
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        SiteSetting::setValue(AttendanceWorkHours::START_TIME_SETTING, '07:00:00');
        SiteSetting::setValue(AttendanceWorkHours::END_TIME_SETTING, '16:00:00');

        $timestamp = now()->toIso8601String();
        $payload = $this->signedDevicePayload([
            'employee_number' => $employee->employee_number,
            'fingerprint_data' => $employee->fingerprint_template,
            'action' => 'TIME-IN',
            'device_serial' => $device->serial_number,
            'reader_id' => $device->reader_id,
            'wifi_mac' => 'AA:BB:CC:DD:EE:71',
            'device_id' => (string) $device->id,
            'mac_address' => 'AA:BB:CC:DD:EE:71',
            'timestamp' => $timestamp,
            'attendance_timestamp' => $timestamp,
            'nonce' => 'dynamic-work-hours-time-in',
        ]);

        $response = (new BiometricController())->processTimeClock(
            Request::create('/api/biometric/time-clock', 'POST', $payload)
        );
        $responseData = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertSame(15, $responseData['data']['late_minutes']);
        $this->assertStringContainsString('LATE by 15 minutes', $responseData['message']);
        $this->assertDatabaseHas('attendance_logs', [
            'employee_profile_id' => $employee->id,
            'status' => 'late',
            'late_minutes' => 15,
        ]);
    }

    private function createBranch(): Branch
    {
        return Branch::create([
            'branch_code' => 'WORK-HOURS-' . uniqid(),
            'branch_name' => 'Work Hours Branch',
            'address' => 'Work Hours Address',
        ]);
    }

    private function signedDevicePayload(array $payload): array
    {
        $values = [
            'POST',
            'api/biometric/time-clock',
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
            $payload['branch'] ?? null,
            $payload['attendance_timestamp'] ?? null,
            $payload['reader_id'] ?? null,
        ];
        $payload['signature'] = hash_hmac(
            'sha256',
            implode('|', array_map(static fn ($value) => is_scalar($value) ? (string) $value : '', $values)),
            self::DEVICE_SECRET
        );

        return $payload;
    }
}
