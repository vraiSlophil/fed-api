<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    /**
     * Allow password-reset link requests for any caller.
     *
     * @return bool Always true because access is controlled by the reset flow itself.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for requesting a password reset by email.
     *
     * @return array Validation constraints keyed by input field name.
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'email' => [
                'description' => 'Email address of the account that should receive a reset link.',
                'example' => 'john@example.com',
            ],
        ];
    }
}
