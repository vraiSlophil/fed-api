<?php

use App\Domain\Metrics\Queries\UserMetricsQueryService;
use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;
use App\Models\Tasks\Task;
use App\Models\Themes\Theme;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

it('counts activity days from theme create, task create, task update, and task validate events', function () {
    $fixedNow = CarbonImmutable::parse('2026-02-23 10:00:00');
    Carbon::setTestNow($fixedNow);
    CarbonImmutable::setTestNow($fixedNow);
    try {
        $user = User::factory()->create();
        $playground = Playground::query()
            ->where('user_id', $user->user_id)
            ->where('is_default', true)
            ->firstOrFail();

        $theme = Theme::factory()->create([
            'owner_id' => $user->user_id,
            'playground_id' => $playground->playground_id,
        ]);
        Theme::query()->where('theme_id', $theme->theme_id)->update([
            'created_at' => $fixedNow->subDays(5),
            'updated_at' => $fixedNow->subDays(5),
        ]);

        $taskCreated = Task::factory()->create([
            'theme_id' => $theme->theme_id,
            'user_id' => $user->user_id,
            'status' => 'todo',
        ]);
        Task::query()->where('task_id', $taskCreated->task_id)->update([
            'created_at' => $fixedNow->subDays(4),
            'updated_at' => $fixedNow->subDays(4),
            'validated_at' => null,
        ]);

        $taskUpdated = Task::factory()->create([
            'theme_id' => $theme->theme_id,
            'user_id' => $user->user_id,
            'status' => 'todo',
        ]);
        Task::query()->where('task_id', $taskUpdated->task_id)->update([
            'created_at' => $fixedNow->subDays(40),
            'updated_at' => $fixedNow->subDays(3),
            'validated_at' => null,
        ]);

        $taskValidated = Task::factory()->create([
            'theme_id' => $theme->theme_id,
            'user_id' => $user->user_id,
            'status' => 'done',
        ]);
        Task::query()->where('task_id', $taskValidated->task_id)->update([
            'created_at' => $fixedNow->subDays(40),
            'updated_at' => $fixedNow->subDays(40),
            'validated_at' => $fixedNow->subDays(2),
        ]);

        $service = new UserMetricsQueryService;
        $metrics = $service->metricsFor($user, '30_days');

        expect($metrics['activity_metrics']['active_days'])->toBe([
            $fixedNow->subDays(5)->toDateString(),
            $fixedNow->subDays(4)->toDateString(),
            $fixedNow->subDays(3)->toDateString(),
            $fixedNow->subDays(2)->toDateString(),
        ])->and($metrics['activity_metrics']['active_days_count'])->toBe(4);
    } finally {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }
});

it('uses a bounded number of queries for metrics computation', function () {
    $user = User::factory()->create();
    $service = new UserMetricsQueryService;

    DB::flushQueryLog();
    DB::enableQueryLog();

    $service->metricsFor($user, '12_months');

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(10);
});
