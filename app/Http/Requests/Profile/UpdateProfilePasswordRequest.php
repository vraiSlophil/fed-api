<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfilePasswordRequest extends FormRequest
{
    /**
     * Allow authenticated users to submit password update payloads.
     *
     * @return bool Always true because account scoping happens at the route/controller level.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for current-password verification and new password fields.
     *
     * @return array Validation constraints keyed by input field name.
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
