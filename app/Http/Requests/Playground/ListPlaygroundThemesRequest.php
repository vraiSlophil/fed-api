<?php

namespace App\Http\Requests\Playground;

use App\Support\Pagination\OffsetPagination;
use Illuminate\Foundation\Http\FormRequest;

class ListPlaygroundThemesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return OffsetPagination::queryRules();
    }
}
