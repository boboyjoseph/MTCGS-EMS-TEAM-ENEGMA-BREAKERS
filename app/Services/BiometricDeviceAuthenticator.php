<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BiometricDeviceAuthenticator
{
    private const BRANCH_SCANNER_SERIALS = ['J62OE23640', 'J320E01822'];

    private const SIGNED_FIELDS = [
        'employee_id',
        'employee_number',
        'device_serial',
        'wifi_mac',
        'device_id',
        'mac_address',
        'timestamp',
        'nonce',
        'action',
        'fingerprint_data',
        'finger_name',
        'branch',
        'attendance_timestamp',
        'fingerprint_scope',
        'reader_id',
    ];

    public function authenticate(Request $request, bool $requireActive = true): array
    {
        $serial = Device::normalizeSerialNumber($request->input('device_serial'));
        $macAddress = Device::normalizeMacAddress($request->input('mac_address'));
        $deviceId = trim((string) $request->input('device_id'));
        $nonce = trim((string) $request->input('nonce'));
        $timestamp = trim((string) $request->input('timestamp'));

        if ($serial === '' || strlen($macAddress) !== 12 || $nonce === '' || $timestamp === '') {
            return $this->reject('A registered device serial, MAC address, timestamp, and nonce are required.');
        }

        $device = Device::query()
            ->whereRaw('UPPER(TRIM(serial_number)) = ?', [$serial])
            ->first();

        if (!$device || ($requireActive && $device->status !== 'active')) {
            return $this->reject('Device serial is not registered or active.');
        }

        $registeredMac = Device::normalizeMacAddress($device->mac_address);
        if ($registeredMac === '' || !hash_equals($registeredMac, $macAddress)) {
            return $this->reject('The device serial and MAC address do not match the registered device.');
        }

        $wifiMac = Device::normalizeMacAddress($request->input('wifi_mac'));
        if ($wifiMac !== '' && !hash_equals($registeredMac, $wifiMac)) {
            return $this->reject('The Wi-Fi MAC address does not match the registered device MAC address.');
        }

        $registeredDeviceId = trim((string) $device->laptop_mac_address);
        $requiresRegisteredDeviceId = in_array($serial, self::BRANCH_SCANNER_SERIALS, true);
        if ($requiresRegisteredDeviceId && $registeredDeviceId === '') {
            return $this->reject('This scanner has no registered device ID. Ask an administrator to provision it.');
        }

        if (($requiresRegisteredDeviceId || $registeredDeviceId !== '')
            && ($deviceId === '' || !hash_equals(strtoupper($registeredDeviceId), strtoupper($deviceId)))) {
            return $this->reject('Device ID does not match the registered device.');
        }

        $registeredReaderId = $this->normalizeReaderId($device->reader_id);
        $readerId = $this->normalizeReaderId($request->input('reader_id'));
        $requiresRegisteredReaderId = in_array($serial, self::BRANCH_SCANNER_SERIALS, true);
        if ($requiresRegisteredReaderId && $registeredReaderId === '') {
            return $this->reject('This branch scanner has no registered physical reader ID. Ask an administrator to provision it.');
        }

        if (($requiresRegisteredReaderId || $registeredReaderId !== '')
            && ($readerId === '' || !hash_equals($registeredReaderId, $readerId))) {
            return $this->reject('Physical reader ID does not match the reader registered to this device.');
        }

        try {
            $secret = (string) $device->api_secret;
        } catch (DecryptException) {
            Log::error('Biometric device credential could not be decrypted; verify the production APP_KEY.', [
                'device_id' => $device->getKey(),
            ]);

            return [
                'error' => 'The server cannot decrypt this device credential. Restore the original APP_KEY or re-provision this device credential.',
                'code' => 503,
            ];
        }

        if ($secret === '') {
            return $this->reject('This device has no provisioned credential. Ask a super administrator to provision it.');
        }

        try {
            $requestTimestamp = \Carbon\Carbon::parse($timestamp);
        } catch (\Throwable $exception) {
            return $this->reject('Invalid device request timestamp.');
        }

        if (abs($requestTimestamp->diffInSeconds(now(), false)) > 300) {
            return $this->reject('Device request timestamp is expired or too far in the future.');
        }

        $signature = strtolower(trim((string) $request->input('signature')));
        $expectedSignature = hash_hmac('sha256', $this->signingPayload($request), $secret);
        if (($signature === '' || !hash_equals($expectedSignature, $signature))
            && !$this->isLocalSuspensionRead($request)) {
            return $this->reject('Invalid device credential signature.');
        }

        $nonceKey = 'biometric-device-nonce:' . $device->getKey() . ':' . hash('sha256', $nonce);
        if (!Cache::add($nonceKey, true, now()->addMinutes(5))) {
            return $this->reject('This device request has already been used.');
        }

        return ['device' => $device];
    }

    private function isLocalSuspensionRead(Request $request): bool
    {
        return app()->environment('local')
            && in_array($request->ip(), ['127.0.0.1', '::1'], true)
            && strtoupper($request->method()) === 'GET'
            && strtolower(trim($request->path(), '/')) === 'api/biometric/suspensions';
    }

    public function signingPayload(Request $request): string
    {
        $values = [
            strtoupper($request->method()),
            $request->path(),
        ];

        foreach (self::SIGNED_FIELDS as $field) {
            $value = $request->input($field);
            if (in_array($field, ['finger_name', 'fingerprint_scope'], true) && !filled($value)) {
                continue;
            }
            $values[] = is_scalar($value) ? (string) $value : '';
        }

        return implode('|', $values);
    }

    public function isCurrentAttendanceTimestamp(string $timestamp, int $allowedDriftSeconds = 60): bool
    {
        try {
            $attendanceTime = \Carbon\Carbon::parse($timestamp);
        } catch (\Throwable) {
            return false;
        }

        return abs($attendanceTime->diffInSeconds(now(), false)) <= $allowedDriftSeconds;
    }

    public function isAttendanceTimestampWithinOfflineWindow(
        string $timestamp,
        int $maxAgeSeconds = 86400,
        int $futureToleranceSeconds = 60
    ): bool {
        try {
            $attendanceTime = \Carbon\Carbon::parse($timestamp);
        } catch (\Throwable) {
            return false;
        }

        $now = now();

        return $attendanceTime->greaterThanOrEqualTo($now->copy()->subSeconds($maxAgeSeconds))
            && $attendanceTime->lessThanOrEqualTo($now->copy()->addSeconds($futureToleranceSeconds));
    }

    private function normalizeReaderId($readerId): string
    {
        return strtoupper(preg_replace('/[^A-F0-9]/i', '', (string) $readerId));
    }

    private function reject(string $message): array
    {
        Log::warning('Biometric device request rejected.', ['reason' => $message]);

        return [
            'error' => $message,
            'code' => 401,
        ];
    }
}
