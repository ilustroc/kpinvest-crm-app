<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class AdminUsersController extends Controller
{
    public function index(Request $r)
    {
        // Si quitaste la columna "# Asesores" de la vista,
        // puedes eliminar ->withCount('asesores')
        $supervisores = User::where('role', 'supervisor')
            ->withCount('asesores')
            ->with(['asesores' => fn($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get();

        $todosSupervisores = $supervisores->pluck('name','id');

        return view('placeholders.administracion', compact('supervisores','todosSupervisores'));
    }

    public function storeSupervisor(Request $r)
    {
        $data = $r->validate([
            'name'     => ['required','string','max:120'],
            'email'    => ['required','email','max:190', Rule::unique('users','email')],
            'password' => ['required','string','min:6'],
        ]);

        User::create([
            'name'           => $data['name'],
            'email'          => $data['email'],
            'password'       => Hash::make($data['password']),
            'role'           => 'supervisor',
            'supervisor_id'  => null,
            // sin is_active
        ]);

        return back()->with('ok','Supervisor creado correctamente.');
    }

    public function storeAsesor(Request $r)
    {
        $data = $r->validate([
            'name'          => ['required','string','max:120'],
            'email'         => ['required','email','max:190', Rule::unique('users','email')],
            'password'      => ['required','string','min:6'],
            'supervisor_id' => ['required','integer', Rule::exists('users','id')->where('role','supervisor')],
        ]);

        User::create([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password'      => Hash::make($data['password']),
            'role'          => 'asesor',
            'supervisor_id' => $data['supervisor_id'],
            // sin is_active
        ]);

        return back()->with('ok','Asesor creado y asignado correctamente.');
    }

    public function reassignAsesor(Request $r, User $id)
    {
        abort_unless($id->role === 'asesor', 404);

        $data = $r->validate([
            'supervisor_id' => ['required','integer', Rule::exists('users','id')->where('role','supervisor')],
        ]);

        $id->update(['supervisor_id' => $data['supervisor_id']]);

        return back()->with('ok','Asesor reasignado correctamente.');
    }

    /** Cambiar contraseña */
    public function updatePassword(Request $r, User $user)
    {
        $data = $r->validate([
            'password' => ['required','string','min:6','confirmed'],
        ]);

        $user->password = Hash::make($data['password']);
        $user->save();

        return back()->with('ok', 'Contraseña actualizada correctamente.');
    }
}
