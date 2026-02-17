<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La lógica fina va en el Rule::in
    }

    public function rules(): array
    {
        $me = $this->user();

        $allowedRoles = match($me->role){
            'administrador','sistemas' => ['supervisor','asesor','soporte','usuario'],
            'supervisor'               => ['asesor','soporte'],
            'soporte'                  => ['usuario'],
            default                    => [],
        };

        return [
            'name'          => ['required', 'string', 'max:120'],
            'email'         => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'role'          => ['required', Rule::in($allowedRoles)],
            'password'      => ['required', 'string', 'min:6'],
            'supervisor_id' => ['nullable', 'integer'],
        ];
    }
}