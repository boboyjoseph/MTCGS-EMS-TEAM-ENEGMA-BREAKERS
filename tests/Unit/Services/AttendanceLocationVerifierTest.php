<?php

namespace Tests\Unit\Services;

use App\Models\Device;
use App\Services\AttendanceLocationVerifier;
use App\Services\BiometricDeviceAuthenticator;
use Illuminate\Http\Request;
use Tests\TestCase;

class AttendanceLocationVerifierTest extends TestCase
{
    public function test_location_check_disabled_keeps_existing_device_behavior(): void
    {
        $device = new Device(['location_check_enabled' => false]);
        $request = Request::create('/api/biometric/time-clock', 'POST');

        $this->assertNull(app(AttendanceLocationVerifier::class)->validate($request, $device));
    }

    public function test_enabled_location_check_accepts_a_fresh_fix_within_radius_and_accuracy(): void
    {
        $now = now()->toIso8601String();
        $device = new Device([
            'location_check_enabled' => true,
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'radius_meters' => 100,
        ]);
        $request = Request::create('/api/biometric/time-clock', 'POST', [
            'gps_latitude' => 14.5995,
            'gps_longitude' => 120.9842,
            'gps_accuracy' => 10,
            'gps_timestamp' => $now,
            'attendance_timestamp' => $now,
        ]);

        $this->assertNull(app(AttendanceLocationVerifier::class)->validate($request, $device));
    }

    public function test_enabled_location_check_rejects_missing_or_stale_location(): void
    {
        $device = new Device([
            'location_check_enabled' => true,
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'radius_meters' => 100,
        ]);
        $verifier = app(AttendanceLocationVerifier::class);

        $missing = Request::create('/api/biometric/time-clock', 'POST');
        $this->assertSame(403, $verifier->validate($missing, $device)['code']);

        $stale = now()->subMinutes(5)->toIso8601String();
        $request = Request::create('/api/biometric/time-clock', 'POST', [
            'gps_latitude' => 14.5995,
            'gps_longitude' => 120.9842,
            'gps_accuracy' => 10,
            'gps_timestamp' => $stale,
            'attendance_timestamp' => now()->toIso8601String(),
        ]);

        $this->assertSame(403, $verifier->validate($request, $device)['code']);
    }

    public function test_enabled_location_check_rejects_outside_radius_or_unconfigured_device(): void
    {
        $device = new Device([
            'location_check_enabled' => true,
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'radius_meters' => 100,
        ]);
        $now = now()->toIso8601String();
        $outside = Request::create('/api/biometric/time-clock', 'POST', [
            'gps_latitude' => 14.61,
            'gps_longitude' => 120.9842,
            'gps_accuracy' => 5,
            'gps_timestamp' => $now,
            'attendance_timestamp' => $now,
        ]);

        $this->assertSame(403, app(AttendanceLocationVerifier::class)->validate($outside, $device)['code']);

        $unconfigured = new Device(['location_check_enabled' => true]);
        $this->assertSame(503, app(AttendanceLocationVerifier::class)->validate($outside, $unconfigured)['code']);
    }

    public function test_gps_values_are_included_in_the_device_signature_when_requested(): void
    {
        $request = Request::create('/api/biometric/time-clock', 'POST', [
            'employee_number' => 'EMP-001',
            'device_serial' => 'SERIAL-001',
            'wifi_mac' => 'AA-BB-CC-DD-EE-FF',
            'device_id' => 'DEVICE-001',
            'mac_address' => 'AA-BB-CC-DD-EE-11',
            'timestamp' => '2026-10-08T08:00:00+08:00',
            'nonce' => 'gps-test',
            'action' => 'TIME-IN',
            'fingerprint_data' => 'template-001',
            'gps_latitude' => '14.5995',
            'gps_longitude' => '120.9842',
            'gps_accuracy' => '8',
            'gps_timestamp' => '2026-10-08T00:00:00.0000000Z',
        ]);
        $authenticator = app(BiometricDeviceAuthenticator::class);
        $signedPayload = $authenticator->signingPayload($request, true);

        $this->assertSame(
            '94b5a8d7457501b425da08f84612a9e8e815fefb421e093daf74b739e0e0d85c',
            hash_hmac('sha256', $signedPayload, 'test-secret')
        );

        $request->merge(['gps_latitude' => '14.6000']);

        $this->assertNotSame($signedPayload, $authenticator->signingPayload($request, true));
        $this->assertNotSame(
            $authenticator->signingPayload($request),
            $authenticator->signingPayload($request, true)
        );
    }
}
