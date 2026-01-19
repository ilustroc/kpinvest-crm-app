<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('promesas_pago', function (Blueprint $table) {
            if (Schema::hasColumn('promesas_pago', 'cumplimiento_estado')) {
                $table->dropColumn('cumplimiento_estado');
            }
        });
    }

    public function down(): void
    {
        Schema::table('promesas_pago', function (Blueprint $table) {
            if (!Schema::hasColumn('promesas_pago', 'cumplimiento_estado')) {
                $table->string('cumplimiento_estado', 20)->default('pendiente')->after('workflow_estado');
            }
        });
    }
};
