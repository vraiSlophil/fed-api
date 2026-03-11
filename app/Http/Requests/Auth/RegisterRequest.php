<?php

namespace App\Http\Requests\Auth;

use App\Models\Auth\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Allow registration attempts for anonymous callers.
     *
     * @return bool Always true because business constraints are handled by validation rules.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for self-service account registration.
     *
     * @return array Validation constraints keyed by input field name.
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised()],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'username' => [
                'description' => 'Public username used to identify the account.',
                'example' => 'john',
            ],
            'email' => [
                'description' => 'Unique email address used for login and verification.',
                'example' => 'john@example.com',
            ],
            'password' => [
                'description' => 'Account password. Must satisfy the configured password strength rules.',
                'example' => 'Str0ng!Passw0rd',
            ],
            'password_confirmation' => [
                'description' => 'Repeat of the password field.',
                'example' => 'Str0ng!Passw0rd',
            ],
        ];
    }
}
