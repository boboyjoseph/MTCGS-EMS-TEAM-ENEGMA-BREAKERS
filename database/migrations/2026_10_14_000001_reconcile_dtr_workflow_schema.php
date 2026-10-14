<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employee_profiles', 'status')) {
            Schema::table('employee_profiles', function (Blueprint $table) {
                $table->string('status')->default('New Hire')->after('date_hired');
            });
        }

        $addedAbsenceFlag = ! Schema::hasColumn('leave_requests', 'is_absent');

        if ($addedAbsenceFlag) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->boolean('is_absent')->default(false)->after('status');
            });
        }

        if ($addedAbsenceFlag) {
            DB::table('leave_requests')
                ->where('status', 'approved')
                ->whereIn('employee_profile_id', function ($query) {
                    $query->select('id')
                        ->from('employee_profiles')
                        ->whereIn('status', ['New Hire', 'New Hired']);
                })
                ->update(['is_absent' => true]);
        }
    }

    public function down(): void
    {
        // Existing columns may predate this reconciliation, so removing them is unsafe.
    }
};
