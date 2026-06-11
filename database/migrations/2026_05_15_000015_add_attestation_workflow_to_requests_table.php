<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql' && Schema::hasTable('requests')) {
            DB::statement("ALTER TABLE `requests` MODIFY `type` ENUM('prolongation','attestation','retard_attestation','absence','autre') NOT NULL");
        }

        Schema::table('requests', function (Blueprint $table) {
            if (! Schema::hasColumn('requests', 'workflow_status')) {
                $table->string('workflow_status', 60)->nullable()->after('status');
            }

            if (! Schema::hasColumn('requests', 'report_path')) {
                $table->string('report_path')->nullable()->after('message');
            }

            if (! Schema::hasColumn('requests', 'report_original_name')) {
                $table->string('report_original_name')->nullable()->after('report_path');
            }

            if (! Schema::hasColumn('requests', 'supervisor_validated_by')) {
                $table->foreignId('supervisor_validated_by')->nullable()->after('processed_by')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('requests', 'supervisor_validated_at')) {
                $table->timestamp('supervisor_validated_at')->nullable()->after('supervisor_validated_by');
            }

            if (! Schema::hasColumn('requests', 'rc_validated_by')) {
                $table->foreignId('rc_validated_by')->nullable()->after('supervisor_validated_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('requests', 'rc_validated_at')) {
                $table->timestamp('rc_validated_at')->nullable()->after('rc_validated_by');
            }

            if (! Schema::hasColumn('requests', 'sent_to_rh_at')) {
                $table->timestamp('sent_to_rh_at')->nullable()->after('rc_validated_at');
            }

            if (! Schema::hasColumn('requests', 'rh_processed_by')) {
                $table->foreignId('rh_processed_by')->nullable()->after('sent_to_rh_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('requests', 'rh_processed_at')) {
                $table->timestamp('rh_processed_at')->nullable()->after('rh_processed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            foreach ([
                'workflow_status',
                'report_path',
                'report_original_name',
                'supervisor_validated_by',
                'supervisor_validated_at',
                'rc_validated_by',
                'rc_validated_at',
                'sent_to_rh_at',
                'rh_processed_by',
                'rh_processed_at',
            ] as $column) {
                if (Schema::hasColumn('requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (DB::getDriverName() === 'mysql' && Schema::hasTable('requests')) {
            DB::statement("ALTER TABLE `requests` MODIFY `type` ENUM('prolongation','attestation','absence','autre') NOT NULL");
        }
    }
};
