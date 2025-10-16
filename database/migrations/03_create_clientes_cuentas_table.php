<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes_cuentas', function (Blueprint $table) {
            $table->id();
            $table->string('numdoc', 30)->nullable();
            $table->string('cuenta', 100)->nullable();
            $table->string('nombre', 180)->nullable();
            $table->string('dpto', 80)->nullable();
            $table->string('operacion', 50)->nullable();
            $table->string('entidad', 120)->nullable();
            $table->string('producto', 120)->nullable();
            $table->string('cosecha', 120)->nullable();
            $table->date('fecha_compra')->nullable();
            $table->date('fecha_castigo')->nullable();
            $table->decimal('deuda_capital', 18, 2)->nullable();
            $table->decimal('interes', 18, 2)->nullable();
            $table->decimal('deuda_total', 18, 2)->nullable();
            $table->string('direccion', 200)->nullable();
            $table->string('provincia', 80)->nullable();
            $table->string('distrito', 80)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes_cuentas');
    }
};
