<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileAvatarRequest extends FormRequest
{
    /**
     * Allow authenticated users to submit avatar update payloads.
     *
     * @return bool Always true because the endpoint already scopes to the current user.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for avatar upload constraints.
     *
     * @return array Validation constraints keyed by input field name.
     */
    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'max:2048'],
        ];
    }
}
