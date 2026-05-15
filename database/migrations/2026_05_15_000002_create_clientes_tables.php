<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes_cuentas', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('numdoc', 30)->nullable();
            $table->string('cuenta', 50)->nullable();
            $table->string('nombre', 180)->nullable();
            $table->string('dpto', 80)->nullable();
            $table->string('operacion', 50)->nullable();
            $table->string('entidad', 120)->nullable();
            $table->string('producto', 120)->nullable();
            $table->string('cosecha', 120)->nullable();
            $table->string('moneda', 10)->nullable();
            $table->date('fecha_compra')->nullable();
            $table->date('fecha_castigo')->nullable();
            $table->decimal('deuda_capital', 18, 2)->nullable();
            $table->decimal('interes', 18, 2)->nullable();
            $table->decimal('deuda_total', 18, 2)->nullable();
            $table->string('direccion', 200)->nullable();
            $table->string('provincia', 80)->nullable();
            $table->string('distrito', 80)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

            $table->index(['numdoc', 'entidad', 'moneda', 'deuda_total', 'deuda_capital'], 'idx_cc_numdoc_search');
        });

        Schema::create('cliente_bloqueos', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('dni', 20);
            $table->string('motivo')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['user_id', 'dni'], 'cliente_bloqueos_user_id_dni_unique');
            $table->index('dni', 'cliente_bloqueos_dni_index');
            $table->foreign('user_id', 'cliente_bloqueos_user_id_foreign')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_bloqueos');
        Schema::dropIfExists('clientes_cuentas');
    }
};
