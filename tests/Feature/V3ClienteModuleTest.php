<?php

namespace Tests\Feature;

use App\Models\CnaSolicitud;
use App\Models\PagoPropia;
use App\Models\PromesaPago;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class V3ClienteModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cliente_lookup_and_suggest_keep_current_contracts(): void
    {
        $user = $this->makeUser('asesor');
        [$dni, $operation] = $this->createClientAccount();

        $this->actingAs($user)
            ->getJson(route('clientes.suggest', ['q' => $operation]))
            ->assertOk()
            ->assertJsonFragment([
                'dni' => $dni,
                'operacion' => $operation,
                'url' => route('clientes.show', $dni),
            ]);

        $this->actingAs($user)
            ->from('/')
            ->get(route('clientes.quick', ['q' => $operation]))
            ->assertRedirect(route('clientes.show', $dni));
    }

    public function test_cliente_quick_lookup_returns_error_when_query_is_ambiguous(): void
    {
        $user = $this->makeUser('asesor');
        $sharedName = 'Cliente Ambiguo '.Str::upper(Str::random(6));

        foreach (range(1, 2) as $index) {
            $operation = 'V3MULTI'.$index.Str::upper(Str::random(4));

            DB::table('clientes_cuentas')->insert([
                'numdoc' => 'V3M'.Str::upper(Str::random(8)),
                'cuenta' => 'CTA-'.$operation,
                'nombre' => $sharedName,
                'dpto' => 'Lima',
                'operacion' => $operation,
                'entidad' => 'BBVA',
                'producto' => 'Producto V3',
                'cosecha' => 'BBVA1',
                'moneda' => 'PEN',
                'fecha_compra' => now()->subYear()->toDateString(),
                'fecha_castigo' => now()->subMonths(6)->toDateString(),
                'deuda_capital' => 1000,
                'interes' => 50,
                'deuda_total' => 1050,
                'direccion' => 'Direccion V3',
                'provincia' => 'Lima',
                'distrito' => 'Lima',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($user)
            ->from('/')
            ->get(route('clientes.quick', ['q' => $sharedName]))
            ->assertRedirect('/')
            ->assertSessionHas('quick_error', 'Hay mas de un cliente. Usa el autocompletado o ingresa DNI/operacion exacta.');
    }

    public function test_cliente_profile_view_receives_accounts_payments_promesas_and_cna(): void
    {
        $user = $this->makeUser('asesor');
        [$dni, $operation] = $this->createClientAccount();

        DB::table('asignar_clientes')->insert([
            'numdoc' => $dni,
            'operacion' => $operation,
            'name' => 'Asesor V3',
        ]);

        $payment = PagoPropia::create([
            'dni' => $dni,
            'operacion' => $operation,
            'entidad' => 'BBVA',
            'nombre_cliente' => 'Cliente V3 Module',
            'monto_pagado' => 75.25,
            'fecha' => now()->toDateString(),
            'gestor' => 'Gestor V3',
            'cosecha' => 'BBVA1',
            'cuenta_recaudo' => 'CTA-V3-PAGO',
        ]);

        $promesa = PromesaPago::create([
            'dni' => $dni,
            'operacion' => $operation,
            'fecha_promesa' => now()->toDateString(),
            'fecha_pago' => now()->addDays(3)->toDateString(),
            'monto' => 120,
            'workflow_estado' => 'pendiente',
            'tipo' => 'cancelacion',
            'telefono' => '999111222',
            'nota' => 'V3 cliente module',
            'user_id' => $user->id,
        ]);

        $cna = CnaSolicitud::create([
            'correlativo' => random_int(100000, 999999),
            'nro_carta' => 'V3'.Str::upper(Str::random(10)),
            'fecha_pago_realizado' => now()->toDateString(),
            'monto_pagado' => 150,
            'observacion' => 'CNA cliente module',
            'dni' => $dni,
            'titular' => 'Cliente V3 Module',
            'producto' => 'Producto V3',
            'operaciones' => [$operation],
            'workflow_estado' => 'pendiente',
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('clientes.show', $dni))
            ->assertOk()
            ->assertViewIs('clientes.show')
            ->assertViewHas('cuentas', function ($cuentas) use ($operation) {
                $account = $cuentas->firstWhere('operacion', $operation);

                return $account
                    && (int) $account->pagos_count === 1
                    && (float) $account->pagos_sum === 75.25
                    && $account->asesor === 'Asesor V3';
            })
            ->assertViewHas('pagos', fn ($pagos) => $pagos->contains('id', $payment->id))
            ->assertViewHas('promesas', fn ($promesas) => $promesas->contains('id', $promesa->id))
            ->assertViewHas('cnasByOperacion', fn ($cnas) => $cnas->has($operation)
                && (int) $cnas->get($operation)->first()->id === (int) $cna->id);
    }

    public function test_cliente_payment_delete_keeps_existing_role_rules(): void
    {
        [$dni, $operation] = $this->createClientAccount();

        $payment = PagoPropia::create([
            'dni' => $dni,
            'operacion' => $operation,
            'entidad' => 'BBVA',
            'nombre_cliente' => 'Cliente V3 Module',
            'monto_pagado' => 45.50,
            'fecha' => now()->toDateString(),
            'gestor' => 'Gestor V3',
            'cosecha' => 'BBVA1',
            'cuenta_recaudo' => 'CTA-V3-PAGO',
        ]);

        $this->actingAs($this->makeUser('asesor'))
            ->from(route('clientes.show', $dni))
            ->post(route('clientes.pagos.delete', $dni), ['ids' => [$payment->id]])
            ->assertForbidden();

        $this->assertDatabaseHas('pagos_propia', ['id' => $payment->id]);

        $this->actingAs($this->makeUser('administrador'))
            ->from(route('clientes.show', $dni))
            ->post(route('clientes.pagos.delete', $dni), ['ids' => [$payment->id]])
            ->assertRedirect(route('clientes.show', $dni))
            ->assertSessionHas('msg', 'Pagos eliminados: 1');

        $this->assertDatabaseMissing('pagos_propia', ['id' => $payment->id]);
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => 'V3 Cliente '.$role.' '.Str::random(8),
            'email' => 'v3-cliente-'.$role.'-'.Str::uuid().'@example.test',
            'password' => Hash::make('secret123'),
            'role' => $role,
            'active' => 1,
        ]);
    }

    private function createClientAccount(): array
    {
        $dni = 'V3C'.Str::upper(Str::random(8));
        $operation = 'V3COP'.Str::upper(Str::random(8));

        DB::table('clientes_cuentas')->insert([
            'numdoc' => $dni,
            'cuenta' => 'CTA-'.$operation,
            'nombre' => 'Cliente V3 Module',
            'dpto' => 'Lima',
            'operacion' => $operation,
            'entidad' => 'BBVA',
            'producto' => 'Producto V3',
            'cosecha' => 'BBVA1',
            'moneda' => 'PEN',
            'fecha_compra' => now()->subYear()->toDateString(),
            'fecha_castigo' => now()->subMonths(6)->toDateString(),
            'deuda_capital' => 1000,
            'interes' => 50,
            'deuda_total' => 1050,
            'direccion' => 'Direccion V3',
            'provincia' => 'Lima',
            'distrito' => 'Lima',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$dni, $operation];
    }
}
