<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Retain historical requests; table removal requires an explicit archival plan.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No schema change is performed by this migration.
    }
};
