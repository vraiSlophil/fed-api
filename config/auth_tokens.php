<?php

return [
    'access_ttl_minutes' => (int)env('ACCESS_TOKEN_TTL_MINUTES', 15),
    'refresh_ttl_days' => (int)env('REFRESH_TOKEN_TTL_DAYS', 30),
];
