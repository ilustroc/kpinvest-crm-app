<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserService;
use App\Http\Requests\StoreUserRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminUsersController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(Request $r)
    {
        $filters = [
            'q' => $r->get('q', ''),
            'inactivos' => $r->boolean('inactivos')
        ];

        $users = $this->userService->getFilteredQuery($r->user(), $filters)->paginate(15);

        $supervisores = User::where('role', 'supervisor')
            ->where('active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('placeholders.administracion', compact('users', 'supervisores'));
    }

    public function store(StoreUserRequest $r)
    {
        $data = $r->validated();
        
        $supId = $this->userService->resolveSupervisorId(
            $r->user(), 
            $data['role'], 
            $r->input('supervisor_id')
        );

        User::create([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password'      => Hash::make($data['password']),
            'role'          => $data['role'],
            'supervisor_id' => $supId,
            'active'        => 1,
        ]);

        return back()->with('ok', 'Usuario creado correctamente.');
    }

    public function toggle(User $user, Request $r)
    {
        $me = $r->user();

        // Reglas críticas de negocio
        if ($user->id === $me->id) return back()->withErrors('No puedes desactivarte a ti mismo.');
        
        if ($user->role === 'administrador' && $user->active) {
            if (User::where('role', 'administrador')->where('active', 1)->count() <= 1) {
                return back()->withErrors('Debe quedar al menos un administrador activo.');
            }
        }

        if (!$this->userService->canManage($me, $user)) {
            return back()->withErrors('No tienes permisos para gestionar este usuario.');
        }

        $user->active = !$user->active;
        $user->save();

        return back()->with('ok', $user->active ? 'Usuario activado.' : 'Usuario desactivado.');
    }

    public function updatePassword(Request $r, User $user)
    {
        if ($r->user()->id !== $user->id && !$this->userService->canManage($r->user(), $user)) {
            return back()->withErrors('No tienes permisos para esta acción.');
        }

        $data = $r->validate(['password' => ['required', 'string', 'min:6', 'confirmed']]);

        $user->password = Hash::make($data['password']);
        $user->save();

        return back()->with('ok', 'Contraseña actualizada correctamente.');
    }
}