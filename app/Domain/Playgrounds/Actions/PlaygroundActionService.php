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

    public function update(Playground $playground, array $validated): Playground
    {
        $playground->update($validated);

        if (isset($validated['is_default']) && $validated['is_default']) {
            $playground->setAsDefault();
        }

        return $playground->fresh();
    }

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
                $defaultPlayground = $this->createDefaultPlayground($user->user_id);
                $user->update(['active_playground_id' => $defaultPlayground->playground_id]);
            }
        });
    }

    public function setAsDefault(User $user, Playground $playground): Playground
    {
        $playground->setAsDefault();
        $user->update(['active_playground_id' => $playground->playground_id]);

        return $playground->fresh();
    }

    private function createDefaultPlayground(string $userId): Playground
    {
        return Playground::create([
            'user_id' => $userId,
            'name' => 'Mon Espace Principal',
            'slug' => 'principal',
            'icon' => 'home',
            'color' => $this->generateRandomColor(),
            'is_default' => true,
        ]);
    }

    private function generateRandomColor(): string
    {
        return sprintf('#%06X', mt_rand(0, 0xFFFFFF));
    }
}
