<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Nuevas columnas destino
        Schema::table('clientes_cuentas', function (Blueprint $t) {
            $t->string('numdoc', 11)->nullable()->after('id');
            $t->string('nombre', 60)->nullable()->after('numdoc');
            $t->string('dpto', 13)->nullable()->after('nombre');
            $t->date('fecha_castigo')->nullable()->after('fecha_compra');
        });

        // 2) Copiar datos desde columnas antiguas
        DB::statement("UPDATE clientes_cuentas SET numdoc = dni, nombre = titular, dpto = departamento");

        // 3) Renombrar y ajustar tipos/largos
        //    (usamos SQL directo para evitar dependencia de doctrine/dbal)
        DB::statement("
            ALTER TABLE clientes_cuentas
              CHANGE COLUMN saldo_capital deuda_capital DECIMAL(18,2) NULL,
              MODIFY COLUMN operacion VARCHAR(20) NULL,
              MODIFY COLUMN producto  VARCHAR(45) NULL,
              MODIFY COLUMN provincia VARCHAR(45) NULL,
              MODIFY COLUMN distrito  VARCHAR(60) NULL,
              MODIFY COLUMN direccion VARCHAR(500) NULL,
              MODIFY COLUMN entidad   VARCHAR(32) NULL,
              MODIFY COLUMN cosecha   VARCHAR(24) NULL
        ");

        // 4) Índices: eliminar antiguos y crear los nuevos
        try { Schema::table('clientes_cuentas', fn(Blueprint $t) => $t->dropUnique('clientes_cuentas_dni_operacion_unique')); } catch (\Throwable $e) {}
        try { Schema::table('clientes_cuentas', fn(Blueprint $t) => $t->dropUnique('clientes_cuentas_concatenar_unique')); } catch (\Throwable $e) {}
        try { Schema::table('clientes_cuentas', fn(Blueprint $t) => $t->dropIndex('clientes_cuentas_dni_index')); } catch (\Throwable $e) {}
        try { Schema::table('clientes_cuentas', fn(Blueprint $t) => $t->dropIndex('clientes_cuentas_operacion_index')); } catch (\Throwable $e) {}

        DB::statement("ALTER TABLE clientes_cuentas ADD UNIQUE KEY cc_numdoc_operacion_unique (numdoc, operacion)");
        DB::statement("ALTER TABLE clientes_cuentas ADD INDEX cc_numdoc_index (numdoc)");
        DB::statement("ALTER TABLE clientes_cuentas ADD INDEX cc_operacion_index (operacion)");

        // 5) Eliminar columnas que ya no van en el nuevo esquema
        Schema::table('clientes_cuentas', function (Blueprint $t) {
            $t->dropColumn([
                'cartera', 'tipo_doc', 'dni', 'concatenar', 'agente', 'titular',
                'anio_castigo', 'moneda', 'departamento', 'zona', 'ubicacion_geografica',
                'edad', 'sexo', 'estado_civil', 'telf1', 'telf2', 'telf3',
                'laboral', 'vehiculos', 'propiedades', 'consolidado_veh_prop',
                'clasificacion', 'score', 'correo_electronico',
                'hasta', 'capital_descuento',
            ]);
        });
        // NOTA: Se mantienen columnas requeridas por el nuevo diseño:
        // OPERACION, NOMBRE, PRODUCTO, DPTO, PROVINCIA, DISTRITO, DIRECCION,
        // ENTIDAD, COSECHA, FECHA_COMPRA, FECHA_CASTIGO, DEUDA_CAPITAL, INTERES, DEUDA_TOTAL,
        // además de id / timestamps.
    }

    public function down(): void
    {
        // Restaurar lo mínimo para volver atrás (best effort)

        // 1) Columnas antiguas básicas
        Schema::table('clientes_cuentas', function (Blueprint $t) {
            $t->string('dni', 30)->nullable()->after('id');
            $t->string('titular', 180)->nullable()->after('dni');
            $t->string('departamento', 80)->nullable()->after('titular');
            $t->string('concatenar', 120)->nullable()->after('operacion');
            $t->decimal('saldo_capital', 18, 2)->nullable()->after('deuda_total');
        });

        // 2) Copiar de nuevas -> antiguas
        DB::statement("UPDATE clientes_cuentas SET dni = numdoc, titular = nombre, departamento = dpto, saldo_capital = deuda_capital");

        // 3) Revertir cambios de tipos/largos
        DB::statement("
            ALTER TABLE clientes_cuentas
              CHANGE COLUMN deuda_capital saldo_capital DECIMAL(18,2) NULL,
              MODIFY COLUMN operacion VARCHAR(50) NULL,
              MODIFY COLUMN producto  VARCHAR(120) NULL,
              MODIFY COLUMN provincia VARCHAR(80) NULL,
              MODIFY COLUMN distrito  VARCHAR(80) NULL,
              MODIFY COLUMN direccion VARCHAR(200) NULL,
              MODIFY COLUMN entidad   VARCHAR(120) NULL,
              MODIFY COLUMN cosecha   VARCHAR(120) NULL
        ");

        // 4) Índices: quitar nuevos y restaurar antiguos
        try { DB::statement("ALTER TABLE clientes_cuentas DROP INDEX cc_numdoc_operacion_unique"); } catch (\Throwable $e) {}
        try { DB::statement("ALTER TABLE clientes_cuentas DROP INDEX cc_numdoc_index"); } catch (\Throwable $e) {}
        try { DB::statement("ALTER TABLE clientes_cuentas DROP INDEX cc_operacion_index"); } catch (\Throwable $e) {}

        try {
            Schema::table('clientes_cuentas', function (Blueprint $t) {
                $t->unique(['dni','operacion'], 'clientes_cuentas_dni_operacion_unique');
                $t->unique('concatenar', 'clientes_cuentas_concatenar_unique');
                $t->index('dni', 'clientes_cuentas_dni_index');
                $t->index('operacion', 'clientes_cuentas_operacion_index');
            });
        } catch (\Throwable $e) {}

        // 5) Eliminar columnas nuevas del esquema simplificado
        Schema::table('clientes_cuentas', function (Blueprint $t) {
            $t->dropColumn(['numdoc','nombre','dpto','fecha_castigo']);
        });
    }
};
