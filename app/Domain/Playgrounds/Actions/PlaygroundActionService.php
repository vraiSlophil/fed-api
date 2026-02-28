<?php

namespace App\Domain\Playgrounds\Actions;

use App\Exceptions\ApiException;
use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlaygroundActionService
{
    /**
     * Create a playground owned by the given user from validated input fields.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  array  $validated  Validated payload extracted from the request.
     * @return Playground Playground instance returned after successful execution.
     */
    public function create(User $user, array $validated): Playground
    {
        $playground = $user->playgrounds()->create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'icon' => $validated['icon'] ?? null,
            'color' => $validated['color'] ?? '#6366F1',
            'background_color' => $validated['background_color'] ?? null,
            'style' => $validated['style'] ?? null,
            'is_default' => $validated['is_default'] ?? false,
        ]);

        if ($playground->is_default) {
            $playground->setAsDefault();
        }

        return $playground;
    }

    /**
     * Update mutable playground fields and optionally promote it as default.
     *
     * @param  Playground  $playground  Playground targeted by the operation.
     * @param  array  $validated  Validated payload extracted from the request.
     * @return Playground Playground instance returned after successful execution.
     */
    public function update(Playground $playground, array $validated): Playground
    {
        $markAsDefault = array_key_exists('is_default', $validated) && (bool) $validated['is_default'] === true;

        if (array_key_exists('is_default', $validated)) {
            unset($validated['is_default']);
        }

        if ($validated !== []) {
            $playground->update($validated);
        }

        if ($markAsDefault) {
            $playground->setAsDefault();
        }

        return $playground->fresh();
    }

    /**
     * Delete a user-owned playground and recreate a default one when needed.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  string  $playgroundId  Identifier of the playground.
     * @return void No return value.
     *
     * @throws \App\Exceptions\ApiException When the operation cannot be completed.
     */
    public function delete(User $user, string $playgroundId): void
    {
        DB::transaction(function () use ($user, $playgroundId): void {
            $playgrounds = Playground::query()
                ->where('user_id', $user->user_id)
                ->lockForUpdate()
                ->get();

            $playground = $playgrounds->firstWhere('playground_id', $playgroundId);
            if (! $playground instanceof Playground) {
                $exception = new ModelNotFoundException;
                $exception->setModel(Playground::class, [$playgroundId]);
                throw $exception;
            }

            if ($playground->is_default) {
                throw new ApiException(
                    messageCode: 'playground.delete.default_forbidden',
                    messageParams: [],
                    status: 400,
                    message: 'Cannot delete default playground'
                );
            }

            $playground->delete();

            if ($playgrounds->count() <= 1) {
                $this->createDefaultPlayground($user->user_id);
            }
        });
    }

    /**
     * Create the fallback default playground used after deleting the previous one.
     *
     * @param  string  $userId  Identifier of the user.
     * @return Playground Playground instance returned after successful execution.
     */
    private function createDefaultPlayground(string $userId): Playground
    {
        return Playground::create([
            'user_id' => $userId,
            'name' => 'Main Workspace',
            'slug' => 'main',
            'icon' => 'home',
            'color' => $this->generateRandomColor(),
            'is_default' => true,
        ]);
    }

    /**
     * Generate a random hex color value for default playground styling.
     *
     * @return string Random `#RRGGBB` color value.
     */
    private function generateRandomColor(): string
    {
        return sprintf('#%06X', mt_rand(0, 0xFFFFFF));
    }
}
