<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Authentification stateless: vérifie les identifiants et retourne le User.
     * Aucun user resolver, aucune session.
     *
     * @throws AuthenticationException
     */
    public function authenticate(): User
    {
        /** @var User|null $user */
        $user = User::where('email', $this->input('email'))->first();

        if (!$user || !Hash::check($this->input('password'), $user->password)) {
            throw new AuthenticationException();
        }

        return $user;
    }
}
