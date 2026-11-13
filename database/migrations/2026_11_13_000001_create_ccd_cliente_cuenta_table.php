<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccd_cliente_cuenta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ccd_cliente_id')
                ->constrained('ccd_clientes')
                ->restrictOnDelete();
            $table->foreignId('cliente_cuenta_id')
                ->constrained('clientes_cuentas')
                ->restrictOnDelete();
            $table->enum('estado', ['candidata', 'confirmada', 'en_revision'])
                ->default('candidata');
            $table->string('metodo_verificacion', 100)->nullable();
            $table->text('evidencia')->nullable();
            $table->foreignId('confirmado_por_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('confirmado_at')->nullable();
            $table->timestamps();

            $table->unique(['ccd_cliente_id', 'cliente_cuenta_id'], 'ccd_cliente_cuenta_unique');
            $table->index(['ccd_cliente_id', 'estado'], 'ccd_cliente_cuenta_ccd_estado_index');
            $table->index(['cliente_cuenta_id', 'estado'], 'ccd_cliente_cuenta_cuenta_estado_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccd_cliente_cuenta');
    }
};
