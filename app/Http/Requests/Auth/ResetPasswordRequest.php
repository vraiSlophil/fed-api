<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    /**
     * Allow password-reset submissions for callers holding a reset token.
     *
     * @return bool Always true because token validity is checked by the reset broker.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for password reset payload fields.
     *
     * @return array Validation constraints keyed by input field name.
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ];
    }
}
