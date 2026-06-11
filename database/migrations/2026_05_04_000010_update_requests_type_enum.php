<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql' && Schema::hasTable('requests')) {
            DB::statement("ALTER TABLE `requests` MODIFY `type` ENUM('prolongation','attestation','absence','autre') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql' && Schema::hasTable('requests')) {
            DB::statement("ALTER TABLE `requests` MODIFY `type` ENUM('prolongation','attestation','autre') NOT NULL");
        }
    }
};
