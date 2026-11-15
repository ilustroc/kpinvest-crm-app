<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ccd_clientes', function (Blueprint $table) {
            $table->index(['cosecha', 'id'], 'ccd_clientes_cosecha_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('ccd_clientes', function (Blueprint $table) {
            $table->dropIndex('ccd_clientes_cosecha_id_index');
        });
    }
};
