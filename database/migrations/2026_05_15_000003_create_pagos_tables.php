<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_lotes', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('tipo', 50);
            $table->string('archivo')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->unsignedInteger('total_registros')->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('pagos_propia', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->unsignedBigInteger('lote_id')->nullable();
            $table->string('dni', 32)->nullable();
            $table->string('operacion', 64)->nullable();
            $table->string('entidad', 150)->nullable();
            $table->string('nombre_cliente')->nullable();
            $table->decimal('monto_pagado', 15, 2)->nullable();
            $table->date('fecha')->nullable();
            $table->string('gestor', 120)->nullable();
            $table->string('cosecha', 100)->nullable();
            $table->string('cuenta_recaudo', 100)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

            $table->index('lote_id', 'fk_pagos_lote');
            $table->foreign('lote_id', 'fk_pagos_propia_lote')
                ->references('id')
                ->on('pagos_lotes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_propia');
        Schema::dropIfExists('pagos_lotes');
    }
};
