<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Allow authenticated users to submit profile field updates.
     *
     * @return bool Always true because the endpoint only targets the current user.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for editable profile identity fields.
     *
     * @return array Validation constraints keyed by input field name.
     */
    public function rules(): array
    {
        $userId = $this->user()?->user_id;

        return [
            'username' => ['sometimes', 'required', 'string', 'max:50', 'unique:users,username,'.$userId.',user_id'],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:users,email,'.$userId.',user_id'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
