<?php

namespace App\Http\Controllers;

use App\Models\Playground;
use App\Models\Task;
use App\Models\Theme;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Builder;
use App\Utils\PaginationUtil;
use App\Exceptions\ApiException;

class PlaygroundController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $playgrounds = $request->user()->playgrounds()
            ->withCount(['themes'])
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();

        return ApiResponse::builder()
            ->success()
            ->messageCode('playground.list.success')
            ->data([
                'playgrounds' => $playgrounds,
            ])
            ->json();
    }

    public function show(Request $request, string $playgroundId): JsonResponse
    {
        $playground = $this->findPlaygroundForUserById($playgroundId, $request->user()->user_id, withThemesCount: true);

        return ApiResponse::builder()
            ->success()
            ->messageCode('playground.show.success')
            ->data([
                'playground' => $playground,
            ])
            ->json();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'slug' => 'nullable|string|max:140',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|size:7|regex:/^#[0-9A-F]{6}$/i',
            'background_color' => 'nullable|string|size:7|regex:/^#[0-9A-F]{6}$/i',
            'style' => 'nullable|array',
            'is_default' => 'boolean',
        ]);

        $playground = $request->user()->playgrounds()->create([
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

        return ApiResponse::builder()
            ->success(201)
            ->messageCode('playground.create.success')
            ->data([
                'playground' => $playground,
            ])
            ->json();
    }

    public function update(Request $request, string $playgroundId): JsonResponse
    {
        $playground = Playground::where('playground_id', $playgroundId)
            ->where('user_id', $request->user()->user_id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'slug' => 'nullable|string|max:140',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|size:7|regex:/^#[0-9A-F]{6}$/i',
            'background_color' => 'nullable|string|size:7|regex:/^#[0-9A-F]{6}$/i',
            'style' => 'nullable|array',
            'is_default' => 'boolean'
        ]);

        $playground->update($validated);

        if (isset($validated['is_default']) && $validated['is_default']) {
            $playground->setAsDefault();
        }

        return ApiResponse::builder()
            ->success()
            ->messageCode('playground.update.success')
            ->data([
                'playground' => $playground->fresh()
            ])
            ->json();
    }

    public function destroy(Request $request, string $playgroundId): JsonResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $playgroundId) {
            $playgrounds = Playground::where('user_id', $user->user_id)
                ->lockForUpdate()
                ->get();

            $playground = $playgrounds->firstWhere('playground_id', $playgroundId);
            if (!$playground) {
                $exception = new ModelNotFoundException();
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

        return ApiResponse::builder()
            ->success()
            ->messageCode('playground.delete.success')
            ->json();
    }

    public function setAsDefault(Request $request, string $playgroundId): JsonResponse
    {
        $playground = Playground::where('playground_id', $playgroundId)
            ->where('user_id', $request->user()->user_id)
            ->firstOrFail();

        $playground->setAsDefault();

        $request->user()->update(['active_playground_id' => $playground->playground_id]);

        return ApiResponse::builder()
            ->success()
            ->messageCode('playground.set_default.success')
            ->data([
                'playground' => $playground->fresh()
            ])
            ->json();
    }

    public function stats(Request $request, string $playgroundId): JsonResponse
    {
        $playground = Playground::where('playground_id', $playgroundId)
            ->where('user_id', $request->user()->user_id)
            ->firstOrFail();

        $stats = [
            'themes' => [
                'total' => $playground->themes()->count(),
                'private' => $playground->themes()->where('visibility', 'private')->count(),
                'shared' => $playground->themes()->where('visibility', 'shared')->count(),
                'public' => $playground->themes()->where('visibility', 'public')->count(),
            ],
            'tasks' => [
                'total' => Task::whereHas('theme', function ($query) use ($playground) {
                    $query->where('playground_id', $playground->playground_id);
                })->count(),
                'todo' => Task::whereHas('theme', function ($query) use ($playground) {
                    $query->where('playground_id', $playground->playground_id);
                })->where('status', 'todo')->count(),
                'in_progress' => Task::whereHas('theme', function ($query) use ($playground) {
                    $query->where('playground_id', $playground->playground_id);
                })->where('status', 'in_progress')->count(),
                'done' => Task::whereHas('theme', function ($query) use ($playground) {
                    $query->where('playground_id', $playground->playground_id);
                })->where('status', 'done')->count(),
            ],
            'completion_rate' => $this->calculateCompletionRate($playground),
            'recent_activity' => $this->getRecentActivity($playground)
        ];

        return ApiResponse::builder()
            ->success()
            ->messageCode('playground.stats.success')
            ->data([
                'playground' => $playground,
                'stats' => $stats
            ])
            ->json();

    }

    private function getPlaygroundCompleteData(Playground $playground): array
    {
        return [
            'playground' => $playground,
            'themes' => $playground->themes()
                ->with([
                    'tasks' => function ($query) {
                        $query->orderBy('position')->orderBy('created_at');
                    },
                    'themeUserPermissions.user:user_id,username,first_name,last_name',
                ])
                ->withCount(['tasks'])
                ->get(),
            'stats' => [
                'themes_count' => $playground->themes()->count(),
                'tasks_count' => Task::whereHas('theme', function ($query) use ($playground) {
                    $query->where('playground_id', $playground->playground_id);
                })->count(),
                'completed_tasks_count' => Task::whereHas('theme', function ($query) use ($playground) {
                    $query->where('playground_id', $playground->playground_id);
                })->where('status', 'done')->count(),
                'completion_rate' => $this->calculateCompletionRate($playground),
            ],
            'recent_activity' => $this->getRecentActivity($playground),
        ];
    }

    private function calculateCompletionRate(Playground $playground): float
    {
        $totalTasks = $this->getTasksQueryForPlayground($playground)->count();

        if ($totalTasks === 0) {
            return 0.0;
        }

        $completedTasks = $this->getTasksQueryForPlayground($playground)
            ->where('status', 'done')
            ->count();

        return (float)number_format(($completedTasks / $totalTasks) * 100.0, 2, '.', '');
    }

    private function getTasksQueryForPlayground(Playground $playground)
    {
        return Task::whereHas('theme', function ($query) use ($playground) {
            $query->where('playground_id', $playground->playground_id);
        });
    }

    private function getRecentActivity(Playground $playground): array
    {
        $recentTasks = Task::whereHas('theme', function ($query) use ($playground) {
            $query->where('playground_id', $playground->playground_id);
        })
            ->with(['theme:theme_id,title', 'user:user_id,username'])
            ->latest('updated_at')
            ->take(10)
            ->get();

        $recentThemes = $playground->themes()
            ->with(['owner:user_id,username'])
            ->latest('updated_at')
            ->take(5)
            ->get();

        return [
            'recent_tasks' => $recentTasks,
            'recent_themes' => $recentThemes,
        ];
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

    public function themes(Request $request, string $playgroundId): JsonResponse
    {
        $playground = $this->findPlaygroundForUserById($playgroundId, $request->user()->user_id);
        return $this->getThemesPaginated($request, $playground);
    }

    public function showBySlug(Request $request, string $slug): JsonResponse
    {
        $playground = $this->findPlaygroundForUserBySlug($slug, $request->user()->user_id, withThemesCount: true);

        return ApiResponse::builder()
            ->success()
            ->messageCode('playground.show.success')
            ->data([
                'playground' => $playground,
            ])
            ->json();
    }

    public function themesBySlug(Request $request, string $slug): JsonResponse
    {
        $playground = $this->findPlaygroundForUserBySlug($slug, $request->user()->user_id);
        return $this->getThemesPaginated($request, $playground);
    }

    private function findPlaygroundForUserById(string $playgroundId, string $userId, bool $withThemesCount = false): Playground
    {
        $query = Playground::where('playground_id', $playgroundId)
            ->where('user_id', $userId);

        if ($withThemesCount) {
            $query->withCount(['themes']);
        }

        return $query->firstOrFail();
    }

    private function findPlaygroundForUserBySlug(string $slug, string $userId, bool $withThemesCount = false): Playground
    {
        $query = Playground::where('slug', $slug)
            ->where('user_id', $userId);

        if ($withThemesCount) {
            $query->withCount(['themes']);
        }

        return $query->firstOrFail();
    }

    private function buildAccessibleThemesQuery(string $playgroundId, string $userId): Builder
    {
        $ownedThemes = Theme::where('playground_id', $playgroundId)
            ->where('owner_id', $userId);

        $sharedThemes = Theme::whereHas('themeUserPermissions', function ($query) use ($userId, $playgroundId) {
            $query->where('user_id', $userId)
                ->where('status', 'active')
                ->where('can_view', true)
                ->where('target_playground_id', $playgroundId);
        })->whereNot('owner_id', $userId);

        return $ownedThemes->union($sharedThemes)->orderBy('created_at', 'desc');
    }

    private function getThemesPaginated(Request $request, Playground $playground): JsonResponse
    {
        $themesQuery = $this->buildAccessibleThemesQuery(
            $playground->playground_id,
            $request->user()->user_id
        );

        $paginator = PaginationUtil::paginate(
            $themesQuery,
            max(1, min(100, (int)$request->input('per_page', 20))),
            max(1, (int)$request->input('page', 1))
        );

        return ApiResponse::builder()
            ->success()
            ->messageCode('playground.themes.list.success')
            ->data([
                'themes' => $paginator['items'],
                'pagination' => $paginator['pagination'],
            ])
            ->json();
    }
}
