<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccd_archivos', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 64);
            $table->string('path', 500)->unique('ccd_archivos_path_unique');
            $table->char('sha256', 64)
                ->charset('ascii')
                ->collation('ascii_bin')
                ->unique('ccd_archivos_sha256_unique');
            $table->unsignedBigInteger('size_bytes');
            $table->string('mime_type', 100);
            $table->string('nombre_original', 500);
            $table->timestamp('verified_at');
            $table->timestamps();
        });

        Schema::table('ccd_clientes', function (Blueprint $table) {
            $table->foreignId('ccd_archivo_id')
                ->nullable()
                ->after('link')
                ->constrained('ccd_archivos')
                ->restrictOnDelete();

            $table->index(['numdoc', 'cosecha'], 'ccd_clientes_numdoc_cosecha_index');
            $table->index(['cosecha', 'pdf'], 'ccd_clientes_cosecha_pdf_index');
        });
    }

    public function down(): void
    {
        Schema::table('ccd_clientes', function (Blueprint $table) {
            $table->dropForeign(['ccd_archivo_id']);
            $table->dropIndex('ccd_clientes_numdoc_cosecha_index');
            $table->dropIndex('ccd_clientes_cosecha_pdf_index');
            $table->dropColumn('ccd_archivo_id');
        });

        Schema::dropIfExists('ccd_archivos');
    }
};
