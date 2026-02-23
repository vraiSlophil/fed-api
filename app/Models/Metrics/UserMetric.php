<?php

namespace App\Models\Metrics;

use App\Models\Auth\User;
use App\Models\Tasks\Task;
use App\Models\Themes\Theme;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMetric extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'total_themes_created', 'total_tasks_created', 'total_tasks_completed', 'current_streak_days', 'longest_streak_days', 'last_activity_date', 'themes_created_this_week', 'themes_created_last_week', 'tasks_created_this_week', 'tasks_created_last_week', 'tasks_completed_this_week', 'tasks_completed_last_week'];

    protected $casts = ['last_activity_date' => 'date', 'updated_at' => 'datetime', 'created_at' => 'datetime'];

    /**
     * Define the belongs-to relationship to user using user_id and user_id keys.
     *
     * @return BelongsTo Relationship used to access the user owning this metric row.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Recompute aggregate user metrics from current theme/task data.
     *
     * @param  string  $userId  Identifier of the user.
     * @return void No return value.
     */
    public static function updateUserMetrics(string $userId): void
    {
        $metrics = self::firstOrCreate(['user_id' => $userId]);

        // Count totals.
        $metrics->total_themes_created = Theme::where('owner_id', $userId)->count();
        $metrics->total_tasks_created = Task::where('user_id', $userId)->count();
        $metrics->total_tasks_completed = Task::where('user_id', $userId)->where('status', 'done')->count();

        // Compute weekly metrics.
        $startOfWeek = now()->startOfWeek();
        $startOfLastWeek = now()->subWeek()->startOfWeek();
        $endOfLastWeek = now()->subWeek()->endOfWeek();

        $metrics->themes_created_this_week = Theme::where('owner_id', $userId)->where('created_at', '>=', $startOfWeek)->count();

        $metrics->themes_created_last_week = Theme::where('owner_id', $userId)->whereBetween('created_at', [$startOfLastWeek, $endOfLastWeek])->count();

        $metrics->tasks_created_this_week = Task::where('user_id', $userId)->where('created_at', '>=', $startOfWeek)->count();

        $metrics->tasks_created_last_week = Task::where('user_id', $userId)->whereBetween('created_at', [$startOfLastWeek, $endOfLastWeek])->count();

        $metrics->tasks_completed_this_week = Task::where('user_id', $userId)->where('status', 'done')->where('validated_at', '>=', $startOfWeek)->count();

        $metrics->tasks_completed_last_week = Task::where('user_id', $userId)->where('status', 'done')->whereBetween('validated_at', [$startOfLastWeek, $endOfLastWeek])->count();

        // Update the latest activity date.
        $lastThemeActivity = Theme::where('owner_id', $userId)->latest('created_at')->first()?->created_at;
        $lastTaskActivity = Task::where('user_id', $userId)->latest('updated_at')->first()?->updated_at;

        if ($lastThemeActivity || $lastTaskActivity) {
            $metrics->last_activity_date = max($lastThemeActivity, $lastTaskActivity)?->toDateString();
        }

        $metrics->save();
    }
}
