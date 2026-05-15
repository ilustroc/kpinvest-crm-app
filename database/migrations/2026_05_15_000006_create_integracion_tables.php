<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignar_clientes', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('numdoc', 20);
            $table->string('operacion', 50);
            $table->string('name', 150);

            $table->unique(['numdoc', 'operacion'], 'unique_asignacion_cliente');
            $table->index('numdoc', 'asignar_clientes_numdoc_index');
            $table->index('operacion', 'asignar_clientes_operacion_index');
        });

        Schema::create('ccd_clientes', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('numdoc', 12);
            $table->string('pdf', 500)->nullable();
            $table->string('cosecha', 100)->nullable();
            $table->string('link', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccd_clientes');
        Schema::dropIfExists('asignar_clientes');
    }
};
