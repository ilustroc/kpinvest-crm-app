<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Drop índices si existen (en tu dump existen con esos nombres)
        try { DB::statement("ALTER TABLE `users` DROP INDEX `users_equipo_id_foreign`"); } catch (\Throwable $e) {}
        try { DB::statement("ALTER TABLE `users` DROP INDEX `users_supervisor_id_foreign`"); } catch (\Throwable $e) {}

        // 2) Quitar columnas si existen
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'equipo_id')) {
                $table->dropColumn('equipo_id');
            }
            if (Schema::hasColumn('users', 'supervisor_id')) {
                $table->dropColumn('supervisor_id');
            }
            if (!Schema::hasColumn('users', 'active')) {
                $table->boolean('active')->default(true)->after('remember_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'equipo_id')) {
                $table->unsignedBigInteger('equipo_id')->nullable()->after('role');
            }
            if (!Schema::hasColumn('users', 'supervisor_id')) {
                $table->unsignedBigInteger('supervisor_id')->nullable()->after('equipo_id');
            }
            if (Schema::hasColumn('users', 'active')) {
                $table->dropColumn('active');
            }
        });
    }
};
