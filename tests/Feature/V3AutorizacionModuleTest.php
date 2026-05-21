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

        $response = $this->actingAs($admin)
            ->get(route('autorizacion', [
                'q' => $dni,
                'status' => 'preaprobada',
            ]))
            ->assertOk()
            ->assertViewIs('autorizacion.index')
            ->assertViewHas('q', $dni)
            ->assertViewHas('status', 'preaprobada')
            ->assertViewHas('isSupervisor', false)
            ->assertViewHas('rows', function ($rows) use ($promesa, $operation) {
                $row = $rows->first(fn ($item) => (int) $item->id === (int) $promesa->id);

                return $row
                    && count($row->cuentas_json ?? []) > 0
                    && collect($row->cuentas_json)->contains(fn ($account) => ($account['operacion'] ?? null) === $operation);
            })
            ->assertViewHas('cnaRows', fn ($rows) => collect($rows->items())->contains(fn ($row) => (int) $row->id === (int) $cna->id))
            ->assertViewHas('prodByOp', fn ($products) => ($products[$operation] ?? null) === 'Producto Autorizacion V3');

        preg_match('/data-operaciones-b64="([^"]+)"/', $response->getContent(), $matches);

        $this->assertNotEmpty($matches[1] ?? null);
        $this->assertSame([$operation], json_decode(base64_decode($matches[1]), true));
    }

    public function test_promesa_detail_accounts_are_resolved_with_trimmed_dni_and_operation(): void
    {
        $admin = $this->makeUser('administrador');
        $supervisor = $this->makeUser('supervisor');
        $asesor = $this->makeUser('asesor', ['supervisor_id' => $supervisor->id]);
        $dni = 'V3ATRIM'.Str::upper(Str::random(6));
        $operation = 'V3AOPTRIM'.Str::upper(Str::random(6));

        $this->createClientAccount($dni, $operation, [
            'numdoc' => " {$dni} ",
            'operacion' => " {$operation} ",
            'deuda_capital' => 2000,
            'deuda_total' => 2193.81,
        ]);

        $promesa = $this->createPromesa($dni, $operation, $asesor, 'preaprobada', $supervisor, false);
        $promesa->forceFill([
            'tipo' => 'convenio',
            'monto' => 0,
            'monto_convenio' => 2193.81,
            'monto_cuota' => 1096.91,
            'nro_cuotas' => 2,
        ])->save();

        DB::table('promesa_cuotas')->insert([
            [
                'promesa_id' => $promesa->id,
                'nro' => 1,
                'fecha' => now()->toDateString(),
                'monto' => 1000,
                'es_balon' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'promesa_id' => $promesa->id,
                'nro' => 2,
                'fecha' => now()->addMonth()->toDateString(),
                'monto' => 1193.81,
                'es_balon' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)
            ->get(route('autorizacion', ['q' => $dni]))
            ->assertOk()
            ->assertViewHas('rows', function ($rows) use ($promesa, $operation) {
                $row = $rows->first(fn ($item) => (int) $item->id === (int) $promesa->id);
                $account = collect($row?->cuentas_json ?? [])->firstWhere('operacion', $operation);

                return $account
                    && (float) ($account['saldo_capital'] ?? 0) === 2000.0
                    && (float) ($account['deuda_total'] ?? 0) === 2193.81;
            });

        $content = $response->getContent();
        preg_match('/data-cuentas-b64="([^"]+)"/', $content, $matches);

        $this->assertNotEmpty($matches[1] ?? null);

        $accounts = json_decode(base64_decode($matches[1]), true);
        $account = collect($accounts)->firstWhere('operacion', $operation);

        $this->assertSame(2000.0, (float) ($account['saldo_capital'] ?? 0));
        $this->assertSame(2193.81, (float) ($account['deuda_total'] ?? 0));

        preg_match('/data-crono-b64="([^"]+)"/', $content, $scheduleMatches);

        $this->assertNotEmpty($scheduleMatches[1] ?? null);

        $schedule = json_decode(base64_decode($scheduleMatches[1]), true);

        $this->assertCount(2, $schedule);
        $this->assertSame(1000.0, (float) ($schedule[0]['monto'] ?? 0));
        $this->assertSame(1193.81, (float) ($schedule[1]['monto'] ?? 0));
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

    private function createClientAccount(?string $dni = null, ?string $operation = null, array $attributes = []): array
    {
        $dni ??= 'V3A'.Str::upper(Str::random(8));
        $operation ??= 'V3AOP'.Str::upper(Str::random(8));

        DB::table('clientes_cuentas')->insert(array_merge([
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
        ], $attributes));

        return [$dni, $operation];
    }

    private function createPromesa(
        string $dni,
        string $operation,
        User $owner,
        string $state,
        ?User $supervisor = null,
        bool $withOperationRelation = true
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

        if ($withOperationRelation) {
            DB::table('promesa_operaciones')->insert([
                'promesa_id' => $promesa->id,
                'operacion' => $operation,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

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
