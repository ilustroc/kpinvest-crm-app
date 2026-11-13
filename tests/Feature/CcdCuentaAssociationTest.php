<?php

namespace Tests\Feature;

use App\Models\CcdCliente;
use App\Models\CcdClienteCuenta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CcdCuentaAssociationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_audit_simulates_then_persists_a_single_candidate_idempotently(): void
    {
        $dni = 'V3CCDA'.Str::upper(Str::random(7));
        $accountId = $this->createAccount($dni, '2744794', 'CAJAAQP1');
        $ccd = $this->createCcd($dni, 'AQP1');

        $this->artisan('ccd:associate-audit', [
            '--ccd-id' => $ccd->id,
        ])->assertExitCode(0);

        $this->assertDatabaseMissing('ccd_cliente_cuenta', [
            'ccd_cliente_id' => $ccd->id,
            'cliente_cuenta_id' => $accountId,
        ]);

        $this->artisan('ccd:associate-audit', [
            '--ccd-id' => $ccd->id,
            '--confirm-operation' => '2744794',
            '--evidence' => 'Operación verificada visualmente en el PDF de prueba.',
        ])->assertExitCode(0);

        $this->assertDatabaseMissing('ccd_cliente_cuenta', [
            'ccd_cliente_id' => $ccd->id,
            'cliente_cuenta_id' => $accountId,
        ]);

        $this->artisan('ccd:associate-audit', [
            '--ccd-id' => $ccd->id,
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('ccd_cliente_cuenta', [
            'ccd_cliente_id' => $ccd->id,
            'cliente_cuenta_id' => $accountId,
            'estado' => CcdClienteCuenta::ESTADO_CANDIDATA,
            'metodo_verificacion' => 'dni_cosecha_normalizada',
        ]);

        $this->artisan('ccd:associate-audit', [
            '--ccd-id' => $ccd->id,
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertSame(1, DB::table('ccd_cliente_cuenta')
            ->where('ccd_cliente_id', $ccd->id)
            ->where('cliente_cuenta_id', $accountId)
            ->count());
    }

    public function test_visual_pdf_confirmation_links_only_the_confirmed_account_in_the_profile(): void
    {
        $user = $this->makeUser();
        $dni = 'V3CCDB'.Str::upper(Str::random(7));
        $confirmedAccountId = $this->createAccount($dni, '2744794', 'CAJAAQP1');
        $otherAccountId = $this->createAccount($dni, '2744795', 'CAJAAQP1');
        $ccd = $this->createCcd($dni, 'AQP1');

        $this->artisan('ccd:associate-audit', [
            '--ccd-id' => $ccd->id,
            '--confirm-operation' => '2744794',
            '--evidence' => 'Operación 2744794 verificada visualmente en el PDF.',
            '--confirmed-by' => $user->id,
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('ccd_cliente_cuenta', [
            'ccd_cliente_id' => $ccd->id,
            'cliente_cuenta_id' => $confirmedAccountId,
            'estado' => CcdClienteCuenta::ESTADO_CONFIRMADA,
            'confirmado_por_user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('ccd_cliente_cuenta', [
            'ccd_cliente_id' => $ccd->id,
            'cliente_cuenta_id' => $otherAccountId,
            'estado' => CcdClienteCuenta::ESTADO_CANDIDATA,
        ]);

        $this->actingAs($user)
            ->get(route('clientes.show', $dni))
            ->assertOk()
            ->assertSee(route('clientes.ccd.show', ['numdoc' => $dni, 'ccdCliente' => $ccd->id]), false)
            ->assertDontSee('CCD sin operación verificada')
            ->assertDontSee('href="https://storage.kpinvest.pe/ccd/', false);
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'Verificador CCD '.Str::random(8),
            'email' => 'verificador-ccd-'.Str::uuid().'@example.test',
            'password' => Hash::make('secret123'),
            'role' => 'administrador',
            'active' => 1,
        ]);
    }

    private function createAccount(string $dni, string $operation, string $cosecha): int
    {
        return (int) DB::table('clientes_cuentas')->insertGetId([
            'numdoc' => $dni,
            'cuenta' => 'CTA-'.$operation,
            'nombre' => 'Cliente CCD Asociación',
            'dpto' => 'Arequipa',
            'operacion' => $operation,
            'entidad' => 'Caja AQP',
            'producto' => 'Producto CCD',
            'cosecha' => $cosecha,
            'moneda' => 'PEN',
            'fecha_compra' => now()->subYear()->toDateString(),
            'fecha_castigo' => now()->subMonths(6)->toDateString(),
            'deuda_capital' => 1000,
            'interes' => 50,
            'deuda_total' => 1050,
            'direccion' => 'Dirección CCD',
            'provincia' => 'Arequipa',
            'distrito' => 'Arequipa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createCcd(string $dni, string $cosecha): CcdCliente
    {
        return CcdCliente::create([
            'numdoc' => $dni,
            'pdf' => 'CCD_'.$cosecha.'-'.Str::random(8).'.pdf',
            'cosecha' => $cosecha,
            'link' => 'https://storage.kpinvest.pe/ccd/secure.pdf',
        ]);
    }
}
