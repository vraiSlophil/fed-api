<?php

namespace App\Http\Requests\Playground;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlaygroundRequest extends FormRequest
{
    private const DOCS_ACTOR_ID = '00000000-0000-0000-0000-000000000000';

    /**
     * Allow playground update payload validation for authenticated requests and docs generation.
     *
     * @return bool True when an authenticated actor or docs generation context is available.
     */
    public function authorize(): bool
    {
        return $this->user() !== null || $this->isGeneratingApiDocs();
    }

    /**
     * Return validation rules for partial playground metadata updates.
     *
     * @return array Validation constraints keyed by input field name.
     */
    public function rules(): array
    {
        /** @var \App\Models\Playgrounds\Playground|null $playground */
        $playground = $this->route('playground');
        $actorId = $this->resolveActorId();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'slug' => [
                'sometimes',
                'string',
                'max:140',
                Rule::unique('playgrounds', 'slug')
                    ->where(fn ($query) => $query->where('user_id', $actorId))
                    ->ignore($playground?->playground_id, 'playground_id'),
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
            'name' => ['description' => 'Updated display name.', 'example' => 'Work'],
            'slug' => ['description' => 'Updated user-scoped slug.', 'example' => 'work'],
            'icon' => ['description' => 'Updated icon identifier.', 'example' => 'briefcase'],
            'color' => ['description' => 'Updated primary color in hex format.', 'example' => '#059669'],
            'background_color' => ['description' => 'Updated background color in hex format.', 'example' => '#ECFDF5'],
            'style' => ['description' => 'Updated JSON style configuration.'],
            'is_default' => ['description' => 'Whether the playground becomes the default one.', 'example' => false],
        ];
    }
}
