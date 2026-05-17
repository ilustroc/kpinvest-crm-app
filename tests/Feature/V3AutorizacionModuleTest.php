<?php

namespace Tests\Feature;

use App\Models\CnaSolicitud;
use App\Models\PagoLote;
use App\Models\PagoPropia;
use App\Models\PromesaPago;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class V3AutorizacionModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_index_uses_current_filters_and_exposes_promesas_and_cna(): void
    {
        $admin = $this->makeUser('administrador');
        $supervisor = $this->makeUser('supervisor');
        $asesor = $this->makeUser('asesor', ['supervisor_id' => $supervisor->id]);
        [$dni, $operation] = $this->createClientAccount();

        $promesa = $this->createPromesa($dni, $operation, $asesor, 'preaprobada', $supervisor);
        $cna = $this->createCna($dni, $operation, $asesor, 'preaprobada', $supervisor);

        $this->actingAs($admin)
            ->get(route('autorizacion', [
                'q' => $dni,
                'status' => 'preaprobada',
            ]))
            ->assertOk()
            ->assertViewIs('autorizacion.index')
            ->assertViewHas('q', $dni)
            ->assertViewHas('status', 'preaprobada')
            ->assertViewHas('isSupervisor', false)
            ->assertViewHas('rows', fn ($rows) => $rows->contains(fn ($row) => (int) $row->id === (int) $promesa->id))
            ->assertViewHas('cnaRows', fn ($rows) => collect($rows->items())->contains(fn ($row) => (int) $row->id === (int) $cna->id))
            ->assertViewHas('prodByOp', fn ($products) => ($products[$operation] ?? null) === 'Producto Autorizacion V3');
    }

    public function test_supervisor_index_keeps_pending_team_visibility_and_asesor_is_blocked(): void
    {
        $supervisor = $this->makeUser('supervisor');
        $asesor = $this->makeUser('asesor', ['supervisor_id' => $supervisor->id]);
        $otherSupervisor = $this->makeUser('supervisor');
        $otherAsesor = $this->makeUser('asesor', ['supervisor_id' => $otherSupervisor->id]);
        [$dni, $operation] = $this->createClientAccount();
        [$otherDni, $otherOperation] = $this->createClientAccount();

        $visiblePromesa = $this->createPromesa($dni, $operation, $asesor, 'pendiente');
        $hiddenPromesaByState = $this->createPromesa($dni, $operation, $asesor, 'preaprobada', $supervisor);
        $hiddenPromesaByTeam = $this->createPromesa($otherDni, $otherOperation, $otherAsesor, 'pendiente');

        $visibleCna = $this->createCna($dni, $operation, $asesor, 'pendiente');
        $hiddenCnaByState = $this->createCna($dni, $operation, $asesor, 'preaprobada', $supervisor);
        $hiddenCnaByTeam = $this->createCna($otherDni, $otherOperation, $otherAsesor, 'pendiente');

        $this->actingAs($supervisor)
            ->get(route('autorizacion'))
            ->assertOk()
            ->assertViewHas('isSupervisor', true)
            ->assertViewHas('rows', function ($rows) use ($visiblePromesa, $hiddenPromesaByState, $hiddenPromesaByTeam) {
                return $rows->contains(fn ($row) => (int) $row->id === (int) $visiblePromesa->id)
                    && ! $rows->contains(fn ($row) => (int) $row->id === (int) $hiddenPromesaByState->id)
                    && ! $rows->contains(fn ($row) => (int) $row->id === (int) $hiddenPromesaByTeam->id);
            })
            ->assertViewHas('cnaRows', function ($rows) use ($visibleCna, $hiddenCnaByState, $hiddenCnaByTeam) {
                $items = collect($rows->items());

                return $items->contains(fn ($row) => (int) $row->id === (int) $visibleCna->id)
                    && ! $items->contains(fn ($row) => (int) $row->id === (int) $hiddenCnaByState->id)
                    && ! $items->contains(fn ($row) => (int) $row->id === (int) $hiddenCnaByTeam->id);
            });

        $this->actingAs($asesor)
            ->get(route('autorizacion'))
            ->assertForbidden();
    }

    public function test_workflow_routes_and_pagos_lookup_keep_current_contracts(): void
    {
        Mail::fake();

        $admin = $this->makeUser('administrador');
        $supervisor = $this->makeUser('supervisor');
        $asesor = $this->makeUser('asesor', ['supervisor_id' => $supervisor->id]);
        [$dni, $operation] = $this->createClientAccount();

        $pending = $this->createPromesa($dni, $operation, $asesor, 'pendiente');

        $this->actingAs($supervisor)
            ->post(route('autorizacion.preaprobar', $pending), ['nota_estado' => 'ok supervisor'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('preaprobada', $pending->fresh()->workflow_estado);

        $preApproved = $this->createPromesa($dni, $operation, $asesor, 'preaprobada', $supervisor);

        $this->actingAs($admin)
            ->post(route('autorizacion.aprobar', $preApproved), ['nota_estado' => 'ok admin'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('aprobada', $preApproved->fresh()->workflow_estado);

        $lote = PagoLote::create([
            'tipo' => 'propia',
            'archivo' => 'autorizacion-v3.csv',
            'usuario_id' => $admin->id,
            'total_registros' => 1,
        ]);

        PagoPropia::create([
            'lote_id' => $lote->id,
            'dni' => $dni,
            'operacion' => $operation,
            'entidad' => 'BBVA',
            'nombre_cliente' => 'Cliente Autorizacion V3',
            'monto_pagado' => 177.25,
            'fecha' => now()->toDateString(),
            'gestor' => 'Gestor V3',
            'cosecha' => 'BBVA1',
            'cuenta_recaudo' => 'CTA-V3',
        ]);

        $this->actingAs($admin)
            ->getJson(route('autorizacion.pagos', $dni))
            ->assertOk()
            ->assertJsonPath('dni', $dni)
            ->assertJsonPath('pagos.0.operacion', $operation)
            ->assertJsonPath('pagos.0.monto_pagado', 177.25);
    }

    private function makeUser(string $role, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'V3 Autorizacion '.$role.' '.Str::random(8),
            'email' => 'v3-autorizacion-'.$role.'-'.Str::uuid().'@example.test',
            'password' => Hash::make('secret123'),
            'role' => $role,
            'active' => 1,
        ], $attributes));
    }

    private function createClientAccount(): array
    {
        $dni = 'V3A'.Str::upper(Str::random(8));
        $operation = 'V3AOP'.Str::upper(Str::random(8));

        DB::table('clientes_cuentas')->insert([
            'numdoc' => $dni,
            'cuenta' => 'CTA-'.$operation,
            'nombre' => 'Cliente Autorizacion V3',
            'dpto' => 'Lima',
            'operacion' => $operation,
            'entidad' => 'BBVA',
            'producto' => 'Producto Autorizacion V3',
            'cosecha' => 'BBVA1',
            'moneda' => 'PEN',
            'fecha_compra' => now()->subYear()->toDateString(),
            'fecha_castigo' => now()->subMonths(6)->toDateString(),
            'deuda_capital' => 1000,
            'interes' => 50,
            'deuda_total' => 1050,
            'direccion' => 'Direccion Autorizacion V3',
            'provincia' => 'Lima',
            'distrito' => 'Lima',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$dni, $operation];
    }

    private function createPromesa(
        string $dni,
        string $operation,
        User $owner,
        string $state,
        ?User $supervisor = null
    ): PromesaPago {
        $promesa = PromesaPago::create([
            'dni' => $dni,
            'operacion' => $operation,
            'fecha_promesa' => now()->toDateString(),
            'fecha_pago' => now()->addDays(3)->toDateString(),
            'monto' => 120,
            'workflow_estado' => $state,
            'tipo' => 'cancelacion',
            'telefono' => '999111222',
            'nota' => 'V3 autorizacion module',
            'user_id' => $owner->id,
            'pre_aprobado_por' => $supervisor?->id,
            'pre_aprobado_at' => $supervisor ? now() : null,
            'aprobado_por' => $state === 'aprobada' ? $owner->id : null,
            'aprobado_at' => $state === 'aprobada' ? now() : null,
        ]);

        DB::table('promesa_operaciones')->insert([
            'promesa_id' => $promesa->id,
            'operacion' => $operation,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $promesa;
    }

    private function createCna(
        string $dni,
        string $operation,
        User $owner,
        string $state,
        ?User $supervisor = null
    ): CnaSolicitud {
        return CnaSolicitud::create([
            'correlativo' => random_int(100000, 999999),
            'nro_carta' => 'V3A'.Str::upper(Str::random(9)),
            'fecha_pago_realizado' => now()->toDateString(),
            'monto_pagado' => 150,
            'observacion' => 'CNA autorizacion module',
            'dni' => $dni,
            'titular' => 'Cliente Autorizacion V3',
            'producto' => 'Producto Autorizacion V3',
            'operaciones' => [$operation],
            'workflow_estado' => $state,
            'user_id' => $owner->id,
            'pre_aprobado_por' => $supervisor?->id,
            'pre_aprobado_at' => $supervisor ? now() : null,
            'aprobado_por' => $state === 'aprobada' ? $owner->id : null,
            'aprobado_at' => $state === 'aprobada' ? now() : null,
        ]);
    }
}
