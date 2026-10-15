<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INCORRECT_SERIAL = 'J62OE23640';
    private const CORRECT_SERIAL = 'J620E23640';

    public function up(): void
    {
        $incorrectDeviceExists = DB::table('devices')
            ->whereRaw('UPPER(TRIM(serial_number)) = ?', [self::INCORRECT_SERIAL])
            ->exists();

        if (!$incorrectDeviceExists) {
            return;
        }

        $correctDeviceExists = DB::table('devices')
            ->whereRaw('UPPER(TRIM(serial_number)) = ?', [self::CORRECT_SERIAL])
            ->exists();

        if ($correctDeviceExists) {
            throw new RuntimeException(
                'Both the old and corrected Iriga scanner serials are registered. Resolve the duplicate device records before running this migration.'
            );
        }

        DB::table('devices')
            ->whereRaw('UPPER(TRIM(serial_number)) = ?', [self::INCORRECT_SERIAL])
            ->update(['serial_number' => self::CORRECT_SERIAL]);
    }

    public function down(): void
    {
        $correctDeviceExists = DB::table('devices')
            ->whereRaw('UPPER(TRIM(serial_number)) = ?', [self::CORRECT_SERIAL])
            ->exists();

        if (!$correctDeviceExists) {
            return;
        }

        $incorrectDeviceExists = DB::table('devices')
            ->whereRaw('UPPER(TRIM(serial_number)) = ?', [self::INCORRECT_SERIAL])
            ->exists();

        if ($incorrectDeviceExists) {
            throw new RuntimeException(
                'Both Iriga scanner serials are registered. Resolve the duplicate device records before rolling back this migration.'
            );
        }

        DB::table('devices')
            ->whereRaw('UPPER(TRIM(serial_number)) = ?', [self::CORRECT_SERIAL])
            ->update(['serial_number' => self::INCORRECT_SERIAL]);
    }
};
