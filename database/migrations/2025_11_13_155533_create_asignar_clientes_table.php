<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignar_clientes', function (Blueprint $table) {
            $table->id();

            // DNI del cliente (numdoc en clientes_cuentas)
            $table->string('numdoc', 20)->index();

            // Operación del cliente
            $table->string('operacion', 50)->index();

            // Nombre del asesor/usuario asignado
            $table->string('name', 150);

            // No timestamps porque tu modelo los tiene desactivados
            // $table->timestamps();

            // Un único registro por (numdoc, operacion)
            $table->unique(['numdoc', 'operacion'], 'unique_asignacion_cliente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignar_clientes');
    }
};
