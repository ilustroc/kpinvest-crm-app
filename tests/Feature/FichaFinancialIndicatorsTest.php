<?php

namespace Tests\Feature;

use App\Models\AsignarCliente;
use App\Models\ClienteCuenta;
use App\Models\PromesaCuota;
use App\Models\PromesaPago;
use App\Models\User;
use App\Services\Cliente\ClienteProfileService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class FichaFinancialIndicatorsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ficha_totals_only_exclude_exact_current_cancelled_assignments_per_account(): void
    {
        $admin = $this->makeUser('administrador', 'Admin indicadores');
        $normal = $this->makeUser('asesor', 'Asesor activo');
        $cancelado = $this->makeUser('asesor', 'CANCELADO');
        $cancelados = $this->makeUser('asesor', ' CANCELADOS ');
        $similar = $this->makeUser('asesor', 'CANCELADO EXTRA');
        $dni = 'IND'.Str::upper(Str::random(8));

        $active = $this->account($dni, 'OP-ACTIVA', 1000, 1500);
        $reassignable = $this->account($dni, 'OP-REASIG', 2000, 3000);
        $plural = $this->account($dni, 'OP-PLURAL', 500, 800);
        $similarLabel = $this->account($dni, 'OP-SIMILAR', 300, 400);
        $duplicatedNormal = $this->account($dni, 'OP-DUP', 400, 600, 'BCP');
        $duplicatedCancelled = $this->account($dni, 'OP-DUP', 700, 900, 'BBVA');

        $this->approvedAssignment($active, $normal, $admin, '2026-01-01 09:00:00');
        $this->approvedAssignment($reassignable, $cancelado, $admin, '2026-01-01 09:00:00');
        $this->approvedAssignment($plural, $cancelados, $admin, '2026-01-01 09:00:00');
        $this->approvedAssignment($similarLabel, $similar, $admin, '2026-01-01 09:00:00');
        $this->approvedAssignment($duplicatedNormal, $normal, $admin, '2026-01-01 09:00:00');
        $this->approvedAssignment($duplicatedCancelled, $cancelado, $admin, '2026-01-01 09:00:00');

        // Una solicitud pendiente posterior no puede cambiar el asesor vigente.
        AsignarCliente::query()->create([
            'numdoc' => $dni,
            'operacion' => $active->operacion,
            'cliente_cuenta_id' => $active->id,
            'name' => 'CANCELADO',
            'solicitante_user_id' => $admin->id,
            'asesor_destino_user_id' => $cancelado->id,
            'fecha_solicitud' => Carbon::parse('2026-01-02 09:00:00', 'America/Lima'),
            'motivo' => 'Pendiente de decisión',
            'estado' => AsignarCliente::ESTADO_PENDIENTE,
            'origen' => AsignarCliente::ORIGEN_MANUAL,
        ]);

        $profile = app(ClienteProfileService::class)->show($dni)->toArray();
        $this->assertSame(1700.0, $profile['totCapital']);
        $this->assertSame(2500.0, $profile['totDeuda']);

        // Una reasignación aprobada desde CANCELADO vuelve a incluir la cuenta.
        $this->approvedAssignment($reassignable, $normal, $admin, '2026-01-03 09:00:00');
        $profileAfterReassignment = app(ClienteProfileService::class)->show($dni)->toArray();
        $this->assertSame(3700.0, $profileAfterReassignment['totCapital']);
        $this->assertSame(5500.0, $profileAfterReassignment['totDeuda']);

        // Una cancelación aprobada posterior vuelve a excluirla.
        $this->approvedAssignment($reassignable, $cancelado, $admin, '2026-01-04 09:00:00');
        $profileAfterCancellation = app(ClienteProfileService::class)->show($dni)->toArray();
        $this->assertSame(1700.0, $profileAfterCancellation['totCapital']);
        $this->assertSame(2500.0, $profileAfterCancellation['totDeuda']);

        $allCancelledDni = 'ALL'.Str::upper(Str::random(8));
        $allCancelled = $this->account($allCancelledDni, 'OP-ALL', 1200, 1800);
        $this->approvedAssignment($allCancelled, $cancelado, $admin, '2026-01-01 09:00:00');
        $allCancelledProfile = app(ClienteProfileService::class)->show($allCancelledDni)->toArray();
        $this->assertSame(0.0, $allCancelledProfile['totCapital']);
        $this->assertSame(0.0, $allCancelledProfile['totDeuda']);

        $noCancelledDni = 'NONE'.Str::upper(Str::random(8));
        $noCancelled = $this->account($noCancelledDni, 'OP-NONE', 1250, 1750);
        $this->approvedAssignment($noCancelled, $normal, $admin, '2026-01-01 09:00:00');
        $noCancelledProfile = app(ClienteProfileService::class)->show($noCancelledDni)->toArray();
        $this->assertSame(1250.0, $noCancelledProfile['totCapital']);
        $this->assertSame(1750.0, $noCancelledProfile['totDeuda']);
    }

    public function test_promise_negotiated_amount_sums_every_quota_and_uses_safe_historical_fallback(): void
    {
        $user = $this->makeUser('asesor', 'Asesor promesas');
        $dni = 'PRM'.Str::upper(Str::random(8));
        $this->account($dni, 'OP-PROMESA', 5000, 6000);

        $twoInstallments = $this->promise($dni, $user, 'convenio', '0.00', '3000.00');
        PromesaCuota::query()->create(['promesa_id' => $twoInstallments->id, 'nro' => 1, 'fecha' => '2026-02-01', 'monto' => '2000.00', 'es_balon' => false]);
        PromesaCuota::query()->create(['promesa_id' => $twoInstallments->id, 'nro' => 2, 'fecha' => '2026-03-01', 'monto' => '1000.00', 'es_balon' => false]);

        $withBalloon = $this->promise($dni, $user, 'convenio_balon', '0.00', '0.00');
        PromesaCuota::query()->create(['promesa_id' => $withBalloon->id, 'nro' => 1, 'fecha' => '2026-02-01', 'monto' => '1000.10', 'es_balon' => false]);
        PromesaCuota::query()->create(['promesa_id' => $withBalloon->id, 'nro' => 2, 'fecha' => '2026-03-01', 'monto' => '2000.20', 'es_balon' => true]);
        PromesaCuota::query()->create(['promesa_id' => $withBalloon->id, 'nro' => 3, 'fecha' => '2026-04-01', 'monto' => '0.30', 'es_balon' => false]);

        $historical = $this->promise($dni, $user, 'convenio', '0.00', '4500.50');
        $cancellation = $this->promise($dni, $user, 'cancelacion', '1250.75', '0.00');
        $otherPromise = $this->promise('OTR'.Str::upper(Str::random(8)), $user, 'convenio', '0.00', '0.00');
        PromesaCuota::query()->create(['promesa_id' => $otherPromise->id, 'nro' => 1, 'fecha' => '2026-02-01', 'monto' => '9999.99', 'es_balon' => false]);

        $promises = PromesaPago::query()->whereIn('id', [$twoInstallments->id, $withBalloon->id, $historical->id, $cancellation->id])
            ->with('cuotas')
            ->get()
            ->keyBy('id');

        $this->assertSame('3000.00', $promises[$twoInstallments->id]->montoNegociado());
        $this->assertSame('3000.60', $promises[$withBalloon->id]->montoNegociado());
        $this->assertSame('4500.50', $promises[$historical->id]->montoNegociado());
        $this->assertSame('1250.75', $promises[$cancellation->id]->montoNegociado());

        $this->actingAs($user)
            ->get(route('clientes.show', $dni))
            ->assertOk()
            ->assertSee('S/ 3,000.00')
            ->assertSee('S/ 3,000.60')
            ->assertSee('S/ 4,500.50')
            ->assertSee('S/ 1,250.75');
    }

    private function account(string $dni, string $operation, float $capital, float $total, string $entity = 'BCP'): ClienteCuenta
    {
        return ClienteCuenta::query()->create([
            'numdoc' => $dni,
            'cuenta' => 'CTA-'.$operation.'-'.$entity,
            'nombre' => 'CLIENTE INDICADORES',
            'operacion' => $operation,
            'entidad' => $entity,
            'producto' => 'PRÉSTAMO',
            'cosecha' => 'TEST',
            'moneda' => 'PEN',
            'deuda_capital' => $capital,
            'interes' => 0,
            'deuda_total' => $total,
        ]);
    }

    private function approvedAssignment(ClienteCuenta $account, User $advisor, User $resolver, string $requestedAt): AsignarCliente
    {
        return AsignarCliente::query()->create([
            'numdoc' => $account->numdoc,
            'operacion' => $account->operacion,
            'cliente_cuenta_id' => $account->id,
            'name' => $advisor->name,
            'solicitante_user_id' => $resolver->id,
            'asesor_destino_user_id' => $advisor->id,
            'fecha_solicitud' => Carbon::parse($requestedAt, 'America/Lima'),
            'motivo' => 'Prueba de indicador',
            'estado' => AsignarCliente::ESTADO_APROBADA,
            'aprobado_por_user_id' => $resolver->id,
            'fecha_aprobacion' => Carbon::parse($requestedAt, 'America/Lima'),
            'origen' => AsignarCliente::ORIGEN_MANUAL,
        ]);
    }

    private function promise(string $dni, User $user, string $type, string $amount, string $convenio): PromesaPago
    {
        return PromesaPago::query()->create([
            'numdoc' => $dni,
            'operacion' => 'OP-PROMESA',
            'fecha_promesa' => '2026-01-01',
            'fecha_pago' => '2026-02-01',
            'monto' => $amount,
            'monto_convenio' => $convenio,
            'monto_cuota' => '0.00',
            'nro_cuotas' => 3,
            'workflow_estado' => 'pendiente',
            'tipo' => $type,
            'telefono' => '999111222',
            'user_id' => $user->id,
        ]);
    }

    private function makeUser(string $role, string $name): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => Str::slug($name).'-'.Str::uuid().'@kpinvest.test',
            'password' => Hash::make('secret123'),
            'role' => $role,
            'active' => 1,
        ]);
    }
}
