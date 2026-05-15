<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promesas_pago', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('dni', 30);
            $table->string('telefono', 30)->nullable();
            $table->string('operacion', 50)->nullable();
            $table->date('fecha_promesa');
            $table->date('fecha_pago')->nullable();
            $table->decimal('monto', 18, 2)->default(0);
            $table->string('workflow_estado', 20)->default('pendiente');
            $table->string('tipo', 20)->default('parcial');
            $table->unsignedInteger('nro_cuotas')->nullable();
            $table->decimal('monto_convenio', 18, 2)->nullable();
            $table->decimal('monto_cuota', 18, 2)->nullable();
            $table->unsignedTinyInteger('cuota_dia')->nullable();
            $table->string('estado', 20)->default('pendiente');
            $table->string('nota', 500)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('pre_aprobado_por')->nullable();
            $table->timestamp('pre_aprobado_at')->nullable();
            $table->text('nota_preaprobacion')->nullable();
            $table->unsignedBigInteger('aprobado_por')->nullable();
            $table->timestamp('aprobado_at')->nullable();
            $table->text('nota_aprobacion')->nullable();
            $table->unsignedBigInteger('rechazado_por')->nullable();
            $table->timestamp('rechazado_at')->nullable();
            $table->string('nota_rechazo', 500)->nullable();

            $table->index('user_id', 'promesas_pago_user_id_foreign');
            $table->index('dni', 'promesas_pago_dni_index');
            $table->index('operacion', 'promesas_pago_operacion_index');
            $table->index('pre_aprobado_por', 'promesas_pago_pre_aprobado_por_foreign');
            $table->index('aprobado_por', 'promesas_pago_aprobado_por_foreign');
            $table->index('rechazado_por', 'promesas_pago_rechazado_por_foreign');
            $table->index('workflow_estado', 'promesas_pago_workflow_estado_index');

            $table->foreign('user_id', 'promesas_pago_user_id_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('pre_aprobado_por', 'promesas_pago_pre_aprobado_por_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('aprobado_por', 'promesas_pago_aprobado_por_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('rechazado_por', 'promesas_pago_rechazado_por_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::create('promesa_cuotas', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->unsignedBigInteger('promesa_id');
            $table->unsignedInteger('nro');
            $table->date('fecha');
            $table->decimal('monto', 12, 2);
            $table->boolean('es_balon')->default(false);
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

            $table->index(['promesa_id', 'nro'], 'promesa_cuotas_promesa_id_nro_index');
            $table->foreign('promesa_id', 'promesa_cuotas_promesa_id_foreign')
                ->references('id')
                ->on('promesas_pago')
                ->cascadeOnDelete();
        });

        Schema::create('promesa_operaciones', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->unsignedBigInteger('promesa_id');
            $table->string('operacion', 50);
            $table->string('cartera', 50)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

            $table->unique(['promesa_id', 'operacion'], 'promesa_operaciones_promesa_id_operacion_unique');
            $table->index('operacion', 'promesa_operaciones_operacion_index');
            $table->index('cartera', 'promesa_operaciones_cartera_index');
            $table->foreign('promesa_id', 'promesa_operaciones_promesa_id_foreign')
                ->references('id')
                ->on('promesas_pago')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promesa_operaciones');
        Schema::dropIfExists('promesa_cuotas');
        Schema::dropIfExists('promesas_pago');
    }
};
