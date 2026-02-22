<?php

namespace App\Http\Requests\ThemeMember;

use Illuminate\Foundation\Http\FormRequest;

class SearchThemeUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['required', 'string', 'min:3'],
            'theme_id' => ['required', 'uuid', 'exists:themes,theme_id'],
        ];
    }
}
