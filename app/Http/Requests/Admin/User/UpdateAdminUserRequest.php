<?php

namespace App\Http\Requests\Admin\User;

use App\Models\Auth\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminUserRequest extends FormRequest
{
    /**
     * Allow request validation; route authorization is enforced by admin policies.
     *
     * @return bool Always true because authorization is handled outside this request object.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for updating an existing admin-managed user account.
     *
     * @return array Validation constraints keyed by input field name.
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'username' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($target->user_id, 'user_id')],
            'email' => ['required', 'email', Rule::unique('users')->ignore($target->user_id, 'user_id')],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'role_power' => ['required', 'exists:roles,power'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
