<?php

namespace App\Observers;

use App\Models\Metrics\UserMetric;
use App\Models\Themes\Theme;

class ThemeObserver
{
    /**
     * Handle side effects triggered after model creation.
     *
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @return void No return value.
     */
    public function created(Theme $theme): void
    {
        UserMetric::updateUserMetrics($theme->owner_id);
    }

    /**
     * Handle side effects triggered after model deletion.
     *
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @return void No return value.
     */
    public function deleted(Theme $theme): void
    {
        UserMetric::updateUserMetrics($theme->owner_id);
    }
}
