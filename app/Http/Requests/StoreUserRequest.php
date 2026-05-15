<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\Authorization\Roles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', User::class);
    }

    public function rules(): array
    {
        $allowedRoles = $this->user()
            ? Roles::creatableUserRoles($this->user())
            : [];

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in($allowedRoles)],
            'password' => ['required', 'string', 'min:6'],
            'supervisor_id' => ['nullable', 'integer'],
        ];
    }
}
