<?php

namespace App\Http\Requests\Auth;

use App\Models\Auth\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class LoginRequest extends FormRequest
{
    /**
     * Allow login attempts for anonymous callers.
     *
     * @return bool Always true because credentials are validated by the authentication step.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for login credentials.
     *
     * @return array Validation constraints keyed by input field name.
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Authenticate credentials from the validated request.
     *
     * @return User User account that matches the submitted credentials.
     *
     * @throws \Illuminate\Auth\AuthenticationException When the operation cannot be completed.
     */
    public function authenticate(): User
    {
        /** @var User|null $user */
        $user = User::where('email', $this->input('email'))->first();

        if (! $user || ! Hash::check($this->input('password'), $user->password)) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
