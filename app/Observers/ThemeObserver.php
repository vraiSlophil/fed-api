<?php

namespace App\Observers;

use App\Models\Theme;
use App\Models\UserMetric;

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
