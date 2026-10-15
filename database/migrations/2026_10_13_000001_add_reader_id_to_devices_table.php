<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('reader_id', 100)->nullable()->after('serial_number');
        });

        DB::table('devices')
            ->whereRaw('UPPER(TRIM(serial_number)) = ?', ['J620E23640'])
            ->update(['reader_id' => '70315bbe-3635-8b41-b4af-04a0bcd6e941']);

        DB::table('devices')
            ->whereRaw('UPPER(TRIM(serial_number)) = ?', ['J320E01822'])
            ->update(['reader_id' => '8c540906-f99a-4e4f-baec-99462e3c24bc']);
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('reader_id');
        });
    }
};
