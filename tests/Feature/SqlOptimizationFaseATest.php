<?php

namespace Tests\Feature;

use App\Models\PromesaPago;
use App\Models\User;
use App\Services\Promesa\PromesaQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SqlOptimizationFaseATest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql']);
    }

    public function test_client_with_multiple_operations_resolves_accurately(): void
    {
        $admin = User::factory()->create([
            'role' => 'administrador',
            'active' => 1,
        ]);

        $dni = '71112223';
        DB::table('clientes_cuentas')->insert([
            [
                'numdoc' => $dni,
                'nombre' => 'CLIENTE MULTI OPERACIONES',
                'operacion' => 'OP-MULTI-101',
                'cuenta' => 'CTA-101',
                'entidad' => 'BANCO A',
                'deuda_capital' => 1000.00,
                'deuda_total' => 1200.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'numdoc' => $dni,
                'nombre' => 'CLIENTE MULTI OPERACIONES',
                'operacion' => 'OP-MULTI-102',
                'cuenta' => 'CTA-102',
                'entidad' => 'BANCO A',
                'deuda_capital' => 2000.00,
                'deuda_total' => 2400.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'numdoc' => $dni,
                'nombre' => 'CLIENTE MULTI OPERACIONES',
                'operacion' => 'OP-MULTI-103',
                'cuenta' => 'CTA-103',
                'entidad' => 'BANCO B',
                'deuda_capital' => 3000.00,
                'deuda_total' => 3600.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $promesa = PromesaPago::create([
            'dni' => $dni,
            'operacion' => 'OP-MULTI-101,OP-MULTI-102',
            'user_id' => $admin->id,
            'monto' => 1500.00,
            'monto_convenio' => 1500.00,
            'tipo' => 'cancelacion',
            'fecha_promesa' => now()->toDateString(),
            'fecha_pago' => now()->addDays(5)->toDateString(),
            'workflow_estado' => 'preaprobada',
            'nro_cuotas' => 1,
        ]);

        $service = app(PromesaQueryService::class);
        $rows = $service->authorizationRows($admin);

        $found = $rows->firstWhere('id', $promesa->id);
        $this->assertNotNull($found);
        $this->assertSame('CLIENTE MULTI OPERACIONES', $found->titular);
        $this->assertSame(3600.00, (float) $found->deuda_total);
        $this->assertSame(3000.00, (float) $found->saldo_capital);
        $this->assertCount(2, $found->cuentas_json);
        $this->assertCount(3, $found->cuentas_cliente_json);
    }

    public function test_two_different_clients_with_same_operation_isolate_strictly(): void
    {
        $admin = User::factory()->create([
            'role' => 'administrador',
            'active' => 1,
        ]);

        $dniA = '88880001';
        $dniB = '88880002';
        $sharedOp = 'OP-SHARED-EXACT-01';

        DB::table('clientes_cuentas')->insert([
            [
                'numdoc' => $dniA,
                'nombre' => 'TITULAR LEGITIMO A',
                'operacion' => $sharedOp,
                'cuenta' => 'CTA-A',
                'entidad' => 'BANCO_A',
                'deuda_capital' => 500.00,
                'deuda_total' => 600.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'numdoc' => $dniB,
                'nombre' => 'TITULAR AJENO B',
                'operacion' => $sharedOp,
                'cuenta' => 'CTA-B',
                'entidad' => 'BANCO_B',
                'deuda_capital' => 9999.00,
                'deuda_total' => 11000.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $promesaA = PromesaPago::create([
            'dni' => $dniA,
            'operacion' => $sharedOp,
            'user_id' => $admin->id,
            'monto' => 400.00,
            'monto_convenio' => 400.00,
            'tipo' => 'cancelacion',
            'fecha_promesa' => now()->toDateString(),
            'fecha_pago' => now()->addDays(3)->toDateString(),
            'workflow_estado' => 'preaprobada',
            'nro_cuotas' => 1,
        ]);

        $service = app(PromesaQueryService::class);
        $rows = $service->authorizationRows($admin);

        $found = $rows->firstWhere('id', $promesaA->id);
        $this->assertNotNull($found);
        $this->assertSame('TITULAR LEGITIMO A', $found->titular);
        $this->assertSame(600.00, (float) $found->deuda_total);
        $this->assertSame(500.00, (float) $found->saldo_capital);

        $accounts = collect($found->cuentas_json);
        $this->assertCount(1, $accounts);
        $this->assertSame('BANCO_A', $accounts->first()['entidad']);
        $this->assertStringNotContainsString('BANCO_B', json_encode($found->cuentas_json));
    }

    public function test_leading_zeros_preserved_as_strings_in_dnis_and_operations(): void
    {
        $admin = User::factory()->create([
            'role' => 'administrador',
            'active' => 1,
        ]);

        $zeroDni = '08765432';
        $zeroOp = '00012345678';

        DB::table('clientes_cuentas')->insert([
            'numdoc' => $zeroDni,
            'nombre' => 'TITULAR CON CEROS A LA IZQUIERDA',
            'operacion' => $zeroOp,
            'cuenta' => 'CTA-0001',
            'entidad' => 'ENTIDAD CEROS',
            'deuda_capital' => 1250.50,
            'deuda_total' => 1400.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $promesa = PromesaPago::create([
            'dni' => $zeroDni,
            'operacion' => $zeroOp,
            'user_id' => $admin->id,
            'monto' => 1000.00,
            'monto_convenio' => 1000.00,
            'tipo' => 'cancelacion',
            'fecha_promesa' => now()->toDateString(),
            'fecha_pago' => now()->addDays(2)->toDateString(),
            'workflow_estado' => 'preaprobada',
            'nro_cuotas' => 1,
        ]);

        $service = app(PromesaQueryService::class);
        $rows = $service->authorizationRows($admin);

        $found = $rows->firstWhere('id', $promesa->id);
        $this->assertNotNull($found);
        $this->assertSame('08765432', (string) $found->dni);
        $this->assertStringStartsWith('08', (string) $found->dni);
        $this->assertSame('00012345678', (string) $found->operacion);
        $this->assertStringStartsWith('000', (string) $found->operacion);
    }

    public function test_operations_with_whitespace_and_nulls_handled_safely(): void
    {
        $admin = User::factory()->create([
            'role' => 'administrador',
            'active' => 1,
        ]);

        $dni = '66554433';
        $op = 'OP-PADDED-99';

        DB::table('clientes_cuentas')->insert([
            'numdoc' => $dni,
            'nombre' => 'CLIENTE PADDED TEST',
            'operacion' => $op,
            'cuenta' => 'CTA-PAD-99',
            'entidad' => 'ENTIDAD PAD',
            'deuda_capital' => 800.00,
            'deuda_total' => 900.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Promesa creada con espacios externos simulados
        $promesa = PromesaPago::create([
            'dni' => ' ' . $dni . ' ',
            'operacion' => '  ' . $op . ' ,  ',
            'user_id' => $admin->id,
            'monto' => 500.00,
            'monto_convenio' => 500.00,
            'tipo' => 'cancelacion',
            'fecha_promesa' => now()->toDateString(),
            'fecha_pago' => now()->addDays(1)->toDateString(),
            'workflow_estado' => 'preaprobada',
            'nro_cuotas' => 1,
        ]);

        $service = app(PromesaQueryService::class);
        $rows = $service->authorizationRows($admin);

        $found = $rows->firstWhere('id', $promesa->id);
        $this->assertNotNull($found);
        $this->assertSame('CLIENTE PADDED TEST', $found->titular);
        $this->assertCount(1, $found->cuentas_json);
        $this->assertSame('OP-PADDED-99', $found->cuentas_json[0]['operacion']);
    }
}
