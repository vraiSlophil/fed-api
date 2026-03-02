<?php

namespace App\Domain\Playgrounds\Queries;

use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;
use Illuminate\Support\Collection;

class PlaygroundQueryService
{
    /**
     * List playgrounds owned by the authenticated user.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @return Collection Collection of matching records.
     */
    public function listForUser(User $user): Collection
    {
        return $user->playgrounds()
            ->withCount(['themes'])
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Find one playground owned by the authenticated user by ID.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  string  $playgroundId  Identifier of the playground.
     * @param  bool  $withThemesCount  Flag indicating whether theme counters must be eager loaded.
     * @return Playground Playground instance returned after successful execution.
     */
    public function findForUserById(User $user, string $playgroundId, bool $withThemesCount = false): Playground
    {
        $query = Playground::query()
            ->where('playground_id', $playgroundId)
            ->where('user_id', $user->user_id);

        if ($withThemesCount) {
            $query->withCount(['themes']);
        }

        return $query->firstOrFail();
    }

    /**
     * Find one playground owned by the authenticated user by slug.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  string  $slug  URL-friendly slug used to identify the resource.
     * @param  bool  $withThemesCount  Flag indicating whether theme counters must be eager loaded.
     * @return Playground Playground instance returned after successful execution.
     */
    public function findForUserBySlug(User $user, string $slug, bool $withThemesCount = false): Playground
    {
        $query = Playground::query()
            ->where('slug', $slug)
            ->where('user_id', $user->user_id);

        if ($withThemesCount) {
            $query->withCount(['themes']);
        }

        return $query->firstOrFail();
    }
}
