<?php

namespace App\Services;

use App\Models\Device;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceLocationVerifier
{
    private const EARTH_RADIUS_METERS = 6371000;

    private const MAX_LOCATION_TIME_DRIFT_SECONDS = 120;

    /**
     * Return an error when an enabled device cannot prove it is within its configured area.
     */
    public function validate(Request $request, Device $device): ?array
    {
        return $this->validateLocation(
            $request,
            $device,
            'Live location is required to record attendance at this kiosk.',
            'The kiosk is outside the school attendance area. Attendance was not recorded.'
        );
    }

    public function validateEnrollment(Request $request, Device $device): ?array
    {
        return $this->validateLocation(
            $request,
            $device,
            'Live location is required to enroll fingerprints at this kiosk.',
            'The kiosk is outside the configured school area. Fingerprint enrollment was not completed.'
        );
    }

    private function validateLocation(
        Request $request,
        Device $device,
        string $missingLocationMessage,
        string $outsideAreaMessage
    ): ?array
    {
        if (! $device->location_check_enabled) {
            return null;
        }

        if ($device->latitude === null || $device->longitude === null || (int) $device->radius_meters < 1) {
            return [
                'error' => 'The school location is not configured for this kiosk. Ask an administrator to set its coordinates and radius.',
                'code' => 503,
            ];
        }

        $latitude = $request->input('gps_latitude');
        $longitude = $request->input('gps_longitude');
        $accuracy = $request->input('gps_accuracy');
        $gpsTimestamp = $request->input('gps_timestamp');
        $attendanceTimestamp = $request->input('attendance_timestamp', $request->input('timestamp'));

        if (! is_numeric($latitude) || ! is_numeric($longitude) || ! is_numeric($accuracy)
            || ! is_string($gpsTimestamp) || ! is_string($attendanceTimestamp)) {
            return [
                'error' => $missingLocationMessage,
                'code' => 403,
            ];
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;
        $accuracy = (float) $accuracy;
        if (! is_finite($latitude) || ! is_finite($longitude) || ! is_finite($accuracy)
            || $latitude < -90 || $latitude > 90
            || $longitude < -180 || $longitude > 180
            || $accuracy < 0) {
            return [
                'error' => 'The kiosk location data is invalid.',
                'code' => 403,
            ];
        }

        try {
            $gpsTime = Carbon::parse($gpsTimestamp);
            $attendanceTime = Carbon::parse($attendanceTimestamp);
        } catch (\Throwable) {
            return [
                'error' => 'The kiosk location timestamp is invalid.',
                'code' => 403,
            ];
        }

        if (abs($gpsTime->diffInSeconds($attendanceTime, false)) > self::MAX_LOCATION_TIME_DRIFT_SECONDS) {
            return [
                'error' => 'The kiosk location is out of date. Wait for a current location and scan again.',
                'code' => 403,
            ];
        }

        $distance = $this->distanceInMeters(
            (float) $device->latitude,
            (float) $device->longitude,
            $latitude,
            $longitude
        );

        if ($distance + $accuracy > (int) $device->radius_meters) {
            return [
                'error' => $outsideAreaMessage,
                'code' => 403,
            ];
        }

        return null;
    }

    private function distanceInMeters(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $latitudeDelta = deg2rad($latitudeB - $latitudeA);
        $longitudeDelta = deg2rad($longitudeB - $longitudeA);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB)) * sin($longitudeDelta / 2) ** 2;

        return self::EARTH_RADIUS_METERS * 2 * asin(min(1, sqrt($a)));
    }
}
