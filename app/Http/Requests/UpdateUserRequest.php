<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\Authorization\Roles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && (bool) $this->user()?->can('update', $target);
    }

    public function rules(): array
    {
        $target = $this->route('user');
        $allowedRoles = $this->user()
            ? Roles::creatableUserRoles($this->user())
            : [];

        if ($target instanceof User && !in_array($target->role, $allowedRoles, true)) {
            $allowedRoles[] = $target->role;
        }

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:190',
                Rule::unique('users', 'email')->ignore($target?->id),
            ],
            'role' => ['required', Rule::in($allowedRoles)],
            'password' => ['nullable', 'string', 'min:6'],
            'supervisor_id' => ['nullable', 'integer'],
        ];
    }
}
