<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use App\Services\Admin\UserStatusService;
use App\Services\UserService;
use App\ViewModels\Admin\UserIndexViewModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminUsersController extends Controller
{
    public function __construct(
        protected UserService $userService,
        private UserStatusService $userStatusService
    ) {}

    public function index(Request $r)
    {
        $filters = [
            'q' => $r->get('q', ''),
            'inactivos' => $r->boolean('inactivos'),
        ];

        $users = $this->userService
            ->getFilteredQuery($r->user(), $filters)
            ->paginate(15);

        $supervisores = User::where('role', 'supervisor')
            ->where('active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $vm = new UserIndexViewModel($users, $supervisores, $filters, $r->user());

        return view('placeholders.administracion', compact('vm', 'users', 'supervisores'));
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
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'supervisor_id' => $supId,
            'active' => 1,
        ]);

        return back()->with('ok', 'Usuario creado correctamente.');
    }

    public function toggle(User $user, Request $r)
    {
        try {
            $message = $this->userStatusService->toggle($r->user(), $user);
            return back()->with('ok', $message);
        } catch (\RuntimeException $e) {
            return back()->withErrors($e->getMessage());
        }
    }

    public function updatePassword(Request $r, User $user)
    {
        if (!$r->user()->can('updatePassword', $user)) {
            return back()->withErrors('No tienes permisos para esta accion.');
        }

        $data = $r->validate(['password' => ['required', 'string', 'min:6', 'confirmed']]);

        $user->password = Hash::make($data['password']);
        $user->save();

        return back()->with('ok', 'Contrasena actualizada correctamente.');
    }
}
