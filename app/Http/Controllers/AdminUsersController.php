<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class AdminUsersController extends Controller
{
    /** Listado + filtros + visibilidad por rol/equipo */
    public function index(Request $r)
    {
        $me   = $r->user();
        $q    = trim((string) $r->get('q',''));
        $showInactivos = $r->boolean('inactivos');

        $query = User::query()
            ->with('supervisor') // para mostrar nombre del supervisor en la tabla
            ->when($q !== '', fn($qq)=>$qq->where(fn($w)=>$w
                ->where('name','like',"%$q%")
                ->orWhere('email','like',"%$q%")))
            ->where('active', $showInactivos ? 0 : 1)
            ->orderBy('name');

        // Visibilidad por rol:
        if (in_array($me->role, ['administrador','sistemas'])) {
            // ve todo
        } elseif ($me->role === 'supervisor') {
            // ve su equipo (asesor/soporte) + él mismo
            $query->where(function($w) use ($me){
                $w->where('supervisor_id', $me->id)
                  ->orWhere('id', $me->id);
            });
        } elseif ($me->role === 'soporte') {
            // ve solo usuarios (si así lo deseas)
            $query->where('role', 'usuario');
        } else {
            abort(403);
        }

        $users = $query->paginate(15);

        // Lista de supervisores para el modal (admin/sistemas ven todos; supervisor se verá a sí mismo)
        $supervisores = User::where('role','supervisor')
            ->where('active',1)
            ->orderBy('name')
            ->get(['id','name','email']);

        return view('placeholders.administracion', compact('users','supervisores'));
    }

    /** Crear usuario (rol restringido por el rol del autenticado) y asignación de equipo */
    public function store(Request $r)
    {
        $me = $r->user();

        // Qué puede crear cada rol
        $allowed = match($me->role){
            'administrador','sistemas' => ['supervisor','asesor','soporte','usuario'],
            'supervisor'               => ['asesor','soporte'],
            'soporte'                  => ['usuario'],
            default                    => [],
        };

        $data = $r->validate([
            'name'     => ['required','string','max:120'],
            'email'    => ['required','email','max:190', Rule::unique('users','email')],
            'role'     => ['required', Rule::in($allowed)],
            'password' => ['required','string','min:6'],
            'supervisor_id' => ['nullable','integer'], // se valida más abajo según casos
        ]);

        // Determinar supervisor_id según quien crea y el rol creado
        $supId = null;

        if (in_array($data['role'], ['asesor','soporte'])) {
            if ($me->role === 'supervisor') {
                $supId = $me->id; // siempre a su propio equipo
            } else {
                // admin/sistemas: debe escoger un supervisor válido
                $supId = (int) $r->input('supervisor_id');
                $exists = User::where('id', $supId)->where('role','supervisor')->where('active',1)->exists();
                abort_unless($exists, 422, 'Supervisor inválido.');
            }
        }
        // si el rol creado es supervisor/usuario -> supervisor_id = null

        User::create([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password'      => Hash::make($data['password']),
            'role'          => $data['role'],
            'supervisor_id' => $supId,
            'active'        => 1, // activo por defecto
        ]);

        return back()->with('ok','Usuario creado correctamente.');
    }

    /** Activar / Desactivar (con reglas y pertenencia a equipo) */
    public function toggle(User $user, Request $r)
    {
        $me = $r->user();

        if ($user->id === $me->id) {
            return back()->withErrors('No puedes desactivarte a ti mismo.');
        }
        if ($user->role === 'administrador' && $user->active) {
            $adminsActivos = User::where('role','administrador')->where('active',1)->count();
            if ($adminsActivos <= 1) {
                return back()->withErrors('Debe quedar al menos un administrador activo.');
            }
        }

        // Autorización
        $canManage =
            in_array($me->role, ['administrador','sistemas'])
            || ($me->role === 'supervisor'
                && in_array($user->role, ['asesor','soporte'])
                && $user->supervisor_id === $me->id)
            || ($me->role === 'soporte' && $user->role === 'usuario');

        if (!$canManage) {
            return back()->withErrors('No tienes permisos para gestionar este usuario.');
        }

        $user->active = !$user->active;
        $user->save();

        return back()->with('ok', $user->active ? 'Usuario activado.' : 'Usuario desactivado.');
    }

    /** Cambiar contraseña (respetando equipo / self-service) */
    public function updatePassword(Request $r, User $user)
    {
        $me = $r->user();

        $canManage =
            $me->id === $user->id
            || in_array($me->role, ['administrador','sistemas'])
            || ($me->role === 'supervisor'
                && in_array($user->role, ['asesor','soporte'])
                && $user->supervisor_id === $me->id)
            || ($me->role === 'soporte' && $user->role === 'usuario');

        if (!$canManage) {
            return back()->withErrors('No tienes permisos para esta acción.');
        }

        $data = $r->validate([
            'password' => ['required','string','min:6','confirmed'],
        ]);

        $user->password = Hash::make($data['password']);
        $user->save();

        return back()->with('ok', 'Contraseña actualizada correctamente.');
    }
}
