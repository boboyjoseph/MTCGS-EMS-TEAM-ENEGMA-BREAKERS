<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->string('pending_employee_number')->nullable();
            $table->unsignedBigInteger('employee_number_change_requested_by')->nullable();
            $table->timestamp('employee_number_change_requested_at')->nullable();
            $table->unsignedBigInteger('employee_number_change_approved_by')->nullable();
            $table->timestamp('employee_number_change_approved_at')->nullable();
            $table->text('employee_number_change_rejection_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'pending_employee_number',
                'employee_number_change_requested_by',
                'employee_number_change_requested_at',
                'employee_number_change_approved_by',
                'employee_number_change_approved_at',
                'employee_number_change_rejection_reason',
            ]);
        });
    }
};