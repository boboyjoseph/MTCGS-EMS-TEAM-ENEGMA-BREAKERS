<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Retain historical records; table removal requires an explicit archival plan.
    }

    public function down(): void
    {
        // No schema change is performed by this migration.
    }
};