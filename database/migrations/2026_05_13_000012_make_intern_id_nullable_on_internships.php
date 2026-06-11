<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('internships')) {
            return;
        }

        // MySQL needs the FK dropped before MODIFY; other drivers (e.g. sqlite
        // used for tests) support a native nullable column change.
        if (DB::getDriverName() === 'mysql') {
            Schema::table('internships', function (Blueprint $table) {
                $table->dropForeign('internships_intern_id_foreign');
            });

            DB::statement('ALTER TABLE `internships` MODIFY `intern_id` BIGINT UNSIGNED NULL');

            Schema::table('internships', function (Blueprint $table) {
                $table->foreign('intern_id')->references('id')->on('interns')->nullOnDelete();
            });
        } else {
            Schema::table('internships', function (Blueprint $table) {
                $table->unsignedBigInteger('intern_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('internships')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            Schema::table('internships', function (Blueprint $table) {
                $table->dropForeign('internships_intern_id_foreign');
            });

            DB::statement('ALTER TABLE `internships` MODIFY `intern_id` BIGINT UNSIGNED NOT NULL');

            Schema::table('internships', function (Blueprint $table) {
                $table->foreign('intern_id')->references('id')->on('interns')->cascadeOnDelete();
            });
        } else {
            Schema::table('internships', function (Blueprint $table) {
                $table->unsignedBigInteger('intern_id')->nullable(false)->change();
            });
        }
    }
};
