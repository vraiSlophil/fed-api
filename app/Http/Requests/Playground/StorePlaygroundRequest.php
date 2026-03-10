<?php

namespace App\Http\Requests\Playground;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlaygroundRequest extends FormRequest
{
    private const DOCS_ACTOR_ID = '00000000-0000-0000-0000-000000000000';

    /**
     * Allow playground creation payload validation for authenticated requests and docs generation.
     *
     * @return bool True when an authenticated actor or docs generation context is available.
     */
    public function authorize(): bool
    {
        return $this->user() !== null || $this->isGeneratingApiDocs();
    }

    /**
     * Return validation rules for creating playground metadata and style options.
     *
     * @return array Validation constraints keyed by input field name.
     */
    public function rules(): array
    {
        $actorId = $this->resolveActorId();

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'string',
                'max:140',
                Rule::unique('playgrounds', 'slug')->where(fn ($query) => $query->where('user_id', $actorId)),
            ],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'size:7', 'regex:/^#[0-9A-F]{6}$/i'],
            'background_color' => ['nullable', 'string', 'size:7', 'regex:/^#[0-9A-F]{6}$/i'],
            'style' => ['nullable', 'array'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Resolve the actor used to scope per-user uniqueness rules.
     *
     * @throws AuthorizationException
     */
    private function resolveActorId(): string
    {
        $actorId = $this->user()?->user_id;

        if ($actorId !== null) {
            return (string) $actorId;
        }

        if ($this->isGeneratingApiDocs()) {
            return self::DOCS_ACTOR_ID;
        }

        throw new AuthorizationException('Authentication required');
    }

    private function isGeneratingApiDocs(): bool
    {
        return (bool) config('app.generating_api_docs');
    }

    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Display name of the playground.', 'example' => 'Home'],
            'slug' => ['description' => 'User-scoped slug used for lookup.', 'example' => 'home'],
            'icon' => ['description' => 'Optional icon identifier used by the frontend.', 'example' => 'house'],
            'color' => ['description' => 'Optional primary color in hex format.', 'example' => '#2563EB'],
            'background_color' => ['description' => 'Optional background color in hex format.', 'example' => '#EFF6FF'],
            'style' => ['description' => 'Optional JSON style configuration for the playground.'],
            'is_default' => ['description' => 'Marks this playground as the user default.', 'example' => true],
        ];
    }
}
