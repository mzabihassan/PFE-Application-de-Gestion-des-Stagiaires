<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE requests MODIFY type ENUM('prolongation', 'attestation', 'absence', 'autre') NOT NULL");
        }

        if (! Schema::hasColumn('requests', 'motif_absence')) {
            Schema::table('requests', function (Blueprint $table) {
                $table->string('motif_absence', 255)->nullable()->after('type');
            });
        }

        if (! Schema::hasColumn('requests', 'absence_generated_at')) {
            Schema::table('requests', function (Blueprint $table) {
                $table->timestamp('absence_generated_at')->nullable()->after('processed_by');
            });
        }
    }

    public function down(): void
    {
        DB::table('requests')
            ->where('type', 'absence')
            ->update(['type' => 'autre']);

        if (Schema::hasColumn('requests', 'motif_absence')) {
            Schema::table('requests', function (Blueprint $table) {
                $table->dropColumn('motif_absence');
            });
        }

        if (Schema::hasColumn('requests', 'absence_generated_at')) {
            Schema::table('requests', function (Blueprint $table) {
                $table->dropColumn('absence_generated_at');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE requests MODIFY type ENUM('prolongation', 'attestation', 'autre') NOT NULL");
        }
    }
};
