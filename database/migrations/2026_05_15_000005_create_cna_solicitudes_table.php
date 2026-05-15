<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cna_solicitudes', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->unsignedBigInteger('correlativo')->nullable();
            $table->string('nro_carta')->unique('cna_solicitudes_nro_carta_unique');
            $table->date('fecha_pago_realizado')->nullable();
            $table->decimal('monto_pagado', 12, 2)->nullable();
            $table->text('observacion')->nullable();
            $table->string('dni', 30);
            $table->string('titular')->nullable();
            $table->string('producto')->nullable();
            $table->longText('operaciones')->charset('utf8mb4')->collation('utf8mb4_bin');
            $table->text('nota')->nullable();
            $table->string('workflow_estado')->default('pendiente');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('pre_aprobado_por')->nullable();
            $table->timestamp('pre_aprobado_at')->nullable();
            $table->unsignedBigInteger('aprobado_por')->nullable();
            $table->timestamp('aprobado_at')->nullable();
            $table->unsignedBigInteger('rechazado_por')->nullable();
            $table->timestamp('rechazado_at')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->string('docx_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index('dni', 'cna_solicitudes_dni_index');
        });

        DB::statement('ALTER TABLE `cna_solicitudes` ADD CONSTRAINT `cna_solicitudes_operaciones_json_check` CHECK (json_valid(`operaciones`))');
    }

    public function down(): void
    {
        Schema::dropIfExists('cna_solicitudes');
    }
};
