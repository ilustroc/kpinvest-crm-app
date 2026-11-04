<?php

namespace Tests\Feature;

use App\Models\CcdCliente;
use App\Models\Pago;
use App\Models\PagoLote;
use App\Models\PromesaPago;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StructuralCleanupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::table('promesa_operaciones')->delete();
        DB::table('promesas_pago')->delete();
        DB::table('pagos')->delete();
        DB::table('pagos_lotes')->delete();
        DB::table('ccd_clientes')->delete();
        DB::table('clientes_cuentas')->delete();
    }

    /**
     * 1. Verificar que promesa_operaciones.cartera y su índice ya no existen,
     * pero las relaciones múltiples operan normalmente.
     */
    public function test_promesa_operaciones_cartera_is_removed_and_multi_operations_work(): void
    {
        $this->assertFalse(
            Schema::hasColumn('promesa_operaciones', 'cartera'),
            'La columna cartera no debe existir en promesa_operaciones'
        );

        $hasIndex = DB::table('information_schema.statistics')
            ->where('table_schema', DB::connection()->getDatabaseName())
            ->where('table_name', 'promesa_operaciones')
            ->where('index_name', 'promesa_operaciones_cartera_index')
            ->exists();

        $this->assertFalse($hasIndex, 'El índice promesa_operaciones_cartera_index no debe existir');

        // Crear usuario y promesa con 2 operaciones
        $user = User::factory()->create(['role' => 'asesor']);

        DB::table('clientes_cuentas')->insert([
            [
                'numdoc' => '45678901',
                'nombre' => 'CLIENTE MULTI OP',
                'operacion' => 'OP-MULTI-01',
                'entidad' => 'BCP',
                'producto' => 'PRESTAMO',
                'cosecha' => '2023',
                'cuenta' => 'CTA-01',
                'deuda_capital' => 1000.00,
                'deuda_total' => 1200.00,
            ],
            [
                'numdoc' => '45678901',
                'nombre' => 'CLIENTE MULTI OP',
                'operacion' => 'OP-MULTI-02',
                'entidad' => 'BCP',
                'producto' => 'TARJETA',
                'cosecha' => '2023',
                'cuenta' => 'CTA-02',
                'deuda_capital' => 2000.00,
                'deuda_total' => 2400.00,
            ],
        ]);

        $promesa = PromesaPago::create([
            'user_id' => $user->id,
            'numdoc' => '45678901',
            'operacion' => 'OP-MULTI-01',
            'monto' => 1500.00,
            'fecha_promesa' => now()->addDays(5)->toDateString(),
            'tipo' => 'cancelacion',
            'workflow_estado' => 'pendiente',
        ]);

        // Insertar las 2 operaciones asociadas sin la columna cartera
        DB::table('promesa_operaciones')->insert([
            ['promesa_id' => $promesa->id, 'operacion' => 'OP-MULTI-01', 'created_at' => now(), 'updated_at' => now()],
            ['promesa_id' => $promesa->id, 'operacion' => 'OP-MULTI-02', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $operaciones = DB::table('promesa_operaciones')
            ->where('promesa_id', $promesa->id)
            ->pluck('operacion')
            ->all();

        $this->assertCount(2, $operaciones);
        $this->assertContains('OP-MULTI-01', $operaciones);
        $this->assertContains('OP-MULTI-02', $operaciones);
    }

    /**
     * 2. Verificar que pagos_lotes.tipo ya no existe y que la importación/asociación
     * de lotes con pagos funciona íntegramente.
     */
    public function test_pagos_lotes_tipo_is_removed_and_lote_relationships_work(): void
    {
        $this->assertFalse(
            Schema::hasColumn('pagos_lotes', 'tipo'),
            'La columna tipo no debe existir en pagos_lotes'
        );

        $admin = User::factory()->create(['role' => 'administrador']);

        // Crear lote sin tipo
        $lote = PagoLote::create([
            'archivo' => 'integracion/pagos/lote_test.csv',
            'usuario_id' => $admin->id,
            'total_registros' => 2,
        ]);

        $this->assertNotNull($lote->id);

        // Asociar pagos al lote
        $pago1 = Pago::create([
            'lote_id' => $lote->id,
            'numdoc' => '10203040',
            'operacion' => 'OP-LOTE-01',
            'entidad' => 'BBVA',
            'nombre_cliente' => 'TITULAR LOTE 1',
            'monto_pagado' => 450.00,
            'fecha' => now()->toDateString(),
            'gestor' => $admin->name,
            'cosecha' => '2023',
        ]);

        $pago2 = Pago::create([
            'lote_id' => $lote->id,
            'numdoc' => '10203041',
            'operacion' => 'OP-LOTE-02',
            'entidad' => 'BBVA',
            'nombre_cliente' => 'TITULAR LOTE 2',
            'monto_pagado' => 600.00,
            'fecha' => now()->toDateString(),
            'gestor' => $admin->name,
            'cosecha' => '2023',
        ]);

        // Verificar relación hasMany y belongsTo
        $this->assertSame(2, $lote->pagos()->count());
        $this->assertSame((int) $lote->id, (int) $pago1->lote->id);
        $this->assertSame((int) $lote->id, (int) $pago2->lote->id);

        // Comprobar que PagoLote::latest('id') funciona sin tipo
        $latest = PagoLote::latest('id')->first();
        $this->assertSame((int) $lote->id, (int) $latest->id);
    }

    /**
     * 3. Verificar que ccd_clientes.numdoc admite VARCHAR(30)
     * y preserva DNI con ceros, RUC y CE/PTP alfanumérico.
     */
    public function test_ccd_clientes_numdoc_supports_up_to_30_chars_and_all_document_types(): void
    {
        $columnType = DB::table('information_schema.columns')
            ->where('table_schema', DB::connection()->getDatabaseName())
            ->where('table_name', 'ccd_clientes')
            ->where('column_name', 'numdoc')
            ->value('column_type');

        $this->assertSame('varchar(30)', strtolower((string) $columnType));

        // 1. DNI con ceros a la izquierda (8 caracteres)
        $dniWithZeros = '00234567';
        // 2. RUC (11 caracteres)
        $ruc = '20601234567';
        // 3. Carné de Extranjería / PTP (hasta 15 caracteres alfanumérico)
        $ce = 'CE-00984512-XYZ';

        CcdCliente::create([
            'numdoc' => $dniWithZeros,
            'pdf' => 'BBVA_00234567.pdf',
            'cosecha' => '2023',
            'link' => 'https://storage.kpinvest.pe/ccd/BBVA_00234567.pdf',
        ]);

        CcdCliente::create([
            'numdoc' => $ruc,
            'pdf' => 'BBVA_20601234567.pdf',
            'cosecha' => '2023',
            'link' => 'https://storage.kpinvest.pe/ccd/BBVA_20601234567.pdf',
        ]);

        CcdCliente::create([
            'numdoc' => $ce,
            'pdf' => 'BBVA_CE00984512XYZ.pdf',
            'cosecha' => '2023',
            'link' => 'https://storage.kpinvest.pe/ccd/BBVA_CE00984512XYZ.pdf',
        ]);

        // Verificar lecturas exactas con ceros a la izquierda
        $foundDni = CcdCliente::where('numdoc', $dniWithZeros)->first();
        $this->assertNotNull($foundDni);
        $this->assertSame($dniWithZeros, $foundDni->numdoc);
        $this->assertSame('00234567', $foundDni->numdoc);

        $foundRuc = CcdCliente::where('numdoc', $ruc)->first();
        $this->assertNotNull($foundRuc);
        $this->assertSame($ruc, $foundRuc->numdoc);

        $foundCe = CcdCliente::where('numdoc', $ce)->first();
        $this->assertNotNull($foundCe);
        $this->assertSame($ce, $foundCe->numdoc);
    }
}
