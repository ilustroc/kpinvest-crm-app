<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pagos_propia', function (Blueprint $t) {
            // Agregar nuevas columnas
            if (!Schema::hasColumn('pagos_propia','caja'))           $t->string('caja', 50)->nullable()->after('gestor');
            if (!Schema::hasColumn('pagos_propia','cosecha'))        $t->string('cosecha', 50)->nullable()->after('caja');
            if (!Schema::hasColumn('pagos_propia','cuenta_recaudo')) $t->string('cuenta_recaudo', 100)->nullable()->after('cosecha');

            // Eliminar columnas solicitadas
            if (Schema::hasColumn('pagos_propia','equipos'))           $t->dropColumn('equipos');
            if (Schema::hasColumn('pagos_propia','producto'))          $t->dropColumn('producto');
            if (Schema::hasColumn('pagos_propia','moneda'))            $t->dropColumn('moneda');
            if (Schema::hasColumn('pagos_propia','fecha_de_pago'))     $t->dropColumn('fecha_de_pago');
            if (Schema::hasColumn('pagos_propia','concatenar'))        $t->dropColumn('concatenar');
            if (Schema::hasColumn('pagos_propia','pagado_en_soles'))   $t->dropColumn('pagado_en_soles');
            if (Schema::hasColumn('pagos_propia','status'))            $t->dropColumn('status');
        });
    }

    public function down(): void
    {
        Schema::table('pagos_propia', function (Blueprint $t) {
            // Revertir (opcional)
            if (!Schema::hasColumn('pagos_propia','equipos'))         $t->string('equipos',150)->nullable();
            if (!Schema::hasColumn('pagos_propia','producto'))        $t->string('producto',80)->nullable();
            if (!Schema::hasColumn('pagos_propia','moneda'))          $t->string('moneda',10)->nullable();
            if (!Schema::hasColumn('pagos_propia','fecha_de_pago'))   $t->date('fecha_de_pago')->nullable();
            if (!Schema::hasColumn('pagos_propia','concatenar'))      $t->string('concatenar',255)->nullable();
            if (!Schema::hasColumn('pagos_propia','pagado_en_soles')) $t->decimal('pagado_en_soles',15,2)->nullable();
            if (!Schema::hasColumn('pagos_propia','status'))          $t->string('status',80)->nullable();

            if (Schema::hasColumn('pagos_propia','cuenta_recaudo')) $t->dropColumn('cuenta_recaudo');
            if (Schema::hasColumn('pagos_propia','cosecha'))        $t->dropColumn('cosecha');
            if (Schema::hasColumn('pagos_propia','caja'))           $t->dropColumn('caja');
        });
    }
};
