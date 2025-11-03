<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class AdminUsersController extends Controller
{
    /** Listado + filtros */
    public function index(Request $r)
    {
        $me   = $r->user();
        $q    = trim((string) $r->get('q',''));
        $showInactivos = $r->boolean('inactivos');

        $query = User::query()
            ->when($q !== '', fn($qq)=>$qq->where(fn($w)=>$w
                ->where('name','like',"%$q%")->orWhere('email','like',"%$q%")))
            ->where('active', $showInactivos ? 0 : 1)
            // Visibilidad por rol:
            ->when($me->role === 'supervisor', fn($qq)=>$qq->whereIn('role',['asesor','soporte']))
            ->when($me->role === 'soporte',    fn($qq)=>$qq->whereIn('role',['usuario']))
            ->orderBy('name');

        $users = $query->paginate(15);

        return view('placeholders.administracion', compact('users'));
    }

    /** Crear usuario (rol restringido por el rol del autenticado) */
    public function store(Request $r)
    {
        $me = $r->user();

        // Roles que puede crear cada rol
        $allowed = match($me->role){
            'administrador' => ['supervisor','asesor','soporte','usuario'],
            'supervisor'    => ['asesor','soporte'],
            'soporte'       => ['usuario'],
            default         => [],
        };

        $data = $r->validate([
            'name'     => ['required','string','max:120'],
            'email'    => ['required','email','max:190', Rule::unique('users','email')],
            'role'     => ['required', Rule::in($allowed)],
            'password' => ['required','string','min:6'],
        ]);

        User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => $data['role'],
            'active'   => true, // activo por defecto
        ]);

        return back()->with('ok','Usuario creado correctamente.');
    }

    /** Activar / Desactivar */
    public function toggle(User $user, Request $r)
    {
        $me = $r->user();

        // Reglas: no desactivarse a sí mismo, debe quedar al menos 1 admin activo
        if ($user->id === $me->id) {
            return back()->withErrors('No puedes desactivarte a ti mismo.');
        }
        if ($user->role === 'administrador' && $user->active) {
            $adminsActivos = User::where('role','administrador')->where('active',1)->count();
            if ($adminsActivos <= 1) {
                return back()->withErrors('Debe quedar al menos un administrador activo.');
            }
        }

        // Autorización básica (quién puede gestionar a quién)
        $canManage = $me->role === 'administrador'
                  || ($me->role === 'supervisor' && in_array($user->role, ['asesor','soporte']))
                  || ($me->role === 'soporte'    && in_array($user->role, ['usuario']));
        if (!$canManage) return back()->withErrors('No tienes permisos para gestionar este usuario.');

        $user->active = !$user->active;
        $user->save();

        return back()->with('ok', $user->active ? 'Usuario activado.' : 'Usuario desactivado.');
    }

    /** Cambiar contraseña */
    public function updatePassword(Request $r, User $user)
    {
        // Permisos mínimos: admin siempre; supervisor/soporte sólo si gestionan ese rol; cada usuario puede su propia clave
        $me = $r->user();
        $canManage = $me->id === $user->id
                  || $me->role === 'administrador'
                  || ($me->role === 'supervisor' && in_array($user->role, ['asesor','soporte']))
                  || ($me->role === 'soporte'    && in_array($user->role, ['usuario']));
        if (!$canManage) return back()->withErrors('No tienes permisos para esta acción.');

        $data = $r->validate([
            'password' => ['required','string','min:6','confirmed'],
        ]);

        $user->password = Hash::make($data['password']);
        $user->save();

        return back()->with('ok', 'Contraseña actualizada correctamente.');
    }
}
