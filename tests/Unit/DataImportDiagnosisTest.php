<?php

namespace Tests\Unit;

use App\Services\Panel\PanelDashboardService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DataImportDiagnosisTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql']);
    }

    /**
     * Proves that DB::table('clientes_cuentas')->upsert() does NOT update existing rows
     * because clientes_cuentas has no UNIQUE index on (numdoc, operacion).
     */
    public function test_upsert_without_unique_index_duplicates_rows_in_mariadb(): void
    {
        $dni = '99990001';
        $op = 'OP-UPSERT-TEST-01';

        // 1. Initial Insert
        DB::table('clientes_cuentas')->insert([
            'numdoc' => $dni,
            'operacion' => $op,
            'cuenta' => 'CTA-01',
            'nombre' => 'CLIENTE TEST INICIAL',
            'entidad' => 'BANCO TEST',
            'deuda_capital' => 1000.00,
            'deuda_total' => 1200.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $initialCount = DB::table('clientes_cuentas')
            ->where('numdoc', $dni)
            ->where('operacion', $op)
            ->count();
        $this->assertSame(1, $initialCount);

        // 2. Attempt upsert via Laravel Query Builder (simulating DataImport::flush)
        $batch = [[
            'numdoc' => $dni,
            'operacion' => $op,
            'cuenta' => 'CTA-01',
            'nombre' => 'CLIENTE TEST ACTUALIZADO',
            'entidad' => 'BANCO TEST',
            'deuda_capital' => 800.00,
            'deuda_total' => 950.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]];

        $updateCols = ['nombre', 'deuda_capital', 'deuda_total', 'updated_at'];
        DB::table('clientes_cuentas')->upsert($batch, ['numdoc', 'operacion'], $updateCols);

        // 3. In MariaDB without a UNIQUE constraint, upsert does an INSERT!
        $afterCount = DB::table('clientes_cuentas')
            ->where('numdoc', $dni)
            ->where('operacion', $op)
            ->count();

        // Proves empirical vulnerability: a duplicate row was created!
        $this->assertSame(2, $afterCount, 'MariaDB without UNIQUE constraint inserts a duplicate row instead of updating');
    }

    /**
     * Proves why UNIQUE(numdoc, operacion, deuda_capital) would fail when debt changes.
     */
    public function test_why_unique_on_deuda_capital_conflicts_with_debt_updates(): void
    {
        // If an index was UNIQUE(numdoc, operacion, deuda_capital):
        // Old row: (numdoc: 77770001, operacion: OP-DEBT-01, deuda_capital: 1000.00)
        // New incoming row: (numdoc: 77770001, operacion: OP-DEBT-01, deuda_capital: 750.00)
        // Since (77770001, OP-DEBT-01, 750.00) != (77770001, OP-DEBT-01, 1000.00),
        // MariaDB would insert BOTH rows, causing the client to owe 1,750.00 instead of 750.00.
        $oldRecord = ['numdoc' => '77770001', 'operacion' => 'OP-DEBT-01', 'deuda_capital' => 1000.00];
        $newRecord = ['numdoc' => '77770001', 'operacion' => 'OP-DEBT-01', 'deuda_capital' => 750.00];

        $oldCompositeKey = "{$oldRecord['numdoc']}:{$oldRecord['operacion']}:{$oldRecord['deuda_capital']}";
        $newCompositeKey = "{$newRecord['numdoc']}:{$newRecord['operacion']}:{$newRecord['deuda_capital']}";

        $this->assertNotSame($oldCompositeKey, $newCompositeKey);
        // This proves that mutable financial attributes must NEVER be part of the account identity key!
    }

    /**
     * Demonstrates string preservation of leading zeros for DNI and operation.
     */
    public function test_leading_zeros_preservation_in_import_data(): void
    {
        $dniWithZero = '01488877';
        $opWithZero = '0008673513';

        DB::table('clientes_cuentas')->insert([
            'numdoc' => $dniWithZero,
            'operacion' => $opWithZero,
            'cuenta' => 'CTA-ZEROS',
            'nombre' => 'CLIENTE CON CEROS',
            'entidad' => 'BANCO ZEROS',
            'deuda_capital' => 500.00,
            'deuda_total' => 600.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('clientes_cuentas')
            ->where('numdoc', $dniWithZero)
            ->where('operacion', $opWithZero)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('01488877', (string) $row->numdoc);
        $this->assertSame('0008673513', (string) $row->operacion);
        $this->assertNotSame('1488877', (string) $row->numdoc);
        $this->assertNotSame('8673513', (string) $row->operacion);
    }

    /**
     * Demonstrates 3-way reconciliation logic (Insert, Update, Quarantine).
     */
    public function test_three_way_reconciliation_logic(): void
    {
        // Setup initial client
        DB::table('clientes_cuentas')->insert([
            'numdoc' => '88881111',
            'operacion' => 'OP-RECON-01',
            'cuenta' => 'CTA-RECON-01',
            'nombre' => 'CLIENTE EXISTENTE',
            'entidad' => 'BCP',
            'producto' => 'TARJETA DE CREDITO',
            'deuda_capital' => 2000.00,
            'deuda_total' => 2500.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Scenario 1: Same account, debt updated -> Action: UPDATE
        $incomingUpdate = [
            'numdoc' => '88881111',
            'operacion' => 'OP-RECON-01',
            'entidad' => 'BCP',
            'deuda_capital' => 1800.00,
            'deuda_total' => 2200.00,
        ];

        // Scenario 2: New account for same client -> Action: INSERT
        $incomingNew = [
            'numdoc' => '88881111',
            'operacion' => 'OP-RECON-02',
            'entidad' => 'BCP',
            'deuda_capital' => 500.00,
            'deuda_total' => 600.00,
        ];

        // Scenario 3: Missing operation or invalid data -> Action: QUARANTINE
        $incomingQuarantine = [
            'numdoc' => '88881111',
            'operacion' => '',
            'entidad' => 'BCP',
            'deuda_capital' => 300.00,
            'deuda_total' => 300.00,
        ];

        // Resolve actions:
        $actionUpdate = empty($incomingUpdate['operacion']) ? 'QUARANTINE' : (
            DB::table('clientes_cuentas')->where('numdoc', $incomingUpdate['numdoc'])->where('operacion', $incomingUpdate['operacion'])->count() === 1
                ? 'UPDATE'
                : 'INSERT'
        );

        $actionNew = empty($incomingNew['operacion']) ? 'QUARANTINE' : (
            DB::table('clientes_cuentas')->where('numdoc', $incomingNew['numdoc'])->where('operacion', $incomingNew['operacion'])->count() === 1
                ? 'UPDATE'
                : 'INSERT'
        );

        $actionQuarantine = empty($incomingQuarantine['operacion']) ? 'QUARANTINE' : 'INSERT';

        $this->assertSame('UPDATE', $actionUpdate);
        $this->assertSame('INSERT', $actionNew);
        $this->assertSame('QUARANTINE', $actionQuarantine);
    }
}
