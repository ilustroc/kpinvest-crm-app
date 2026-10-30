<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('pagos_propia', 'pagos');
    }

    public function down(): void
    {
        Schema::rename('pagos', 'pagos_propia');
    }
};
