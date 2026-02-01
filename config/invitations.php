<?php

return [
    'expires_days' => (int) env('INVITATION_EXPIRES_DAYS', 7),
    'expiration_notification_max_attempts' => (int) env('INVITATION_EXPIRE_NOTIFICATION_MAX_ATTEMPTS', 3),
];
