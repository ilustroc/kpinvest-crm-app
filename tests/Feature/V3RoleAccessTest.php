<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
use Throwable;

class V3RoleAccessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_final_roles_have_expected_route_access(): void
    {
        $dni = $this->sampleDniOrSkip();

        $cases = [
            'administrador' => [
                'ok' => ['/', '/dashboard', '/administracion', '/reportes/pagos', '/integracion/pagos', '/autorizacion', route('clientes.show', $dni)],
                'forbidden' => [],
            ],
            'supervisor' => [
                'ok' => ['/', '/dashboard', '/administracion', '/reportes/pagos', '/integracion/pagos', '/autorizacion', route('clientes.show', $dni)],
                'forbidden' => [],
            ],
            'asesor' => [
                'ok' => ['/', '/dashboard', route('clientes.show', $dni)],
                'forbidden' => ['/administracion', '/reportes/pagos', '/integracion/pagos', '/autorizacion'],
            ],
            'soporte' => [
                'ok' => ['/', '/dashboard', '/administracion', '/reportes/pagos', '/integracion/pagos', route('clientes.show', $dni)],
                'forbidden' => ['/autorizacion'],
            ],
        ];

        foreach ($cases as $role => $expectations) {
            $user = $this->makeUser($role);

            foreach ($expectations['ok'] as $uri) {
                $this->actingAs($user)->get($uri)->assertOk();
            }

            foreach ($expectations['forbidden'] as $uri) {
                $this->actingAs($user)->get($uri)->assertForbidden();
            }
        }
    }

    public function test_inactive_user_cannot_login(): void
    {
        $password = 'secret-v3-role';
        $user = $this->makeUser('asesor', [
            'active' => 0,
            'password' => Hash::make($password),
        ]);

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => $password,
        ])
            ->assertSessionHasErrors('email')
            ->assertRedirect();

        $this->assertGuest();
    }

    public function test_removed_roles_are_rejected_when_creating_users(): void
    {
        $admin = $this->makeUser('administrador');

        foreach (['sistemas', 'usuario'] as $role) {
            $email = 'v3-invalid-'.$role.'-'.Str::uuid().'@example.test';

            $this->actingAs($admin)
                ->from(route('administracion.index'))
                ->post(route('administracion.usuarios.store'), [
                    'name' => 'V3 Invalid '.$role,
                    'email' => $email,
                    'role' => $role,
                    'password' => 'secret123',
                ])
                ->assertRedirect(route('administracion.index'))
                ->assertSessionHasErrors('role');

            $this->assertDatabaseMissing('users', ['email' => $email]);
        }
    }

    private function makeUser(string $role, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'V3 '.$role.' '.Str::random(8),
            'email' => 'v3-'.$role.'-'.Str::uuid().'@example.test',
            'password' => Hash::make('secret123'),
            'role' => $role,
            'active' => 1,
        ], $attributes));
    }

    private function sampleDniOrSkip(): string
    {
        try {
            $dni = DB::table('clientes_cuentas')->whereNotNull('numdoc')->value('numdoc');
        } catch (Throwable $e) {
            $this->markTestSkipped('Tabla clientes_cuentas no disponible: '.$e->getMessage());
        }

        if (!$dni) {
            $this->markTestSkipped('No hay cliente local para validar accesos por rol.');
        }

        return (string) $dni;
    }
}
