<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El ENUM se valida y limita exclusivamente en 000007. Esta migración
        // conserva active y sólo elimina la columna heredada is_active.
        if (! Schema::hasColumn('users', 'is_active')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'is_active')) {
            return;
        }

        // Es una reversión necesariamente aproximada: el valor histórico propio
        // de is_active se perdió al eliminarse y se reconstruye desde active.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('active');
        });

        \Illuminate\Support\Facades\DB::statement('UPDATE `users` SET `is_active` = `active`');
    }
};
