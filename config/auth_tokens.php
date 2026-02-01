<?php

return [
    'access_ttl_minutes' => (int) env('ACCESS_TOKEN_TTL_MINUTES', 15),
    'refresh_ttl_days' => (int) env('REFRESH_TOKEN_TTL_DAYS', 30),
    'refresh_reuse_grace_seconds' => (int) env('REFRESH_TOKEN_REUSE_GRACE_SECONDS', 30),
];
