<?php

namespace App\Observers;

use App\Models\Metrics\UserMetric;
use App\Models\Themes\Theme;

class ThemeObserver
{
    public function created(Theme $theme): void
    {
        UserMetric::updateUserMetrics($theme->owner_id);
    }

    public function deleted(Theme $theme): void
    {
        UserMetric::updateUserMetrics($theme->owner_id);
    }
}
