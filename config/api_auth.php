<?php

return [
    'access_token_ttl_minutes' => (int) env('ACCESS_TOKEN_TTL_MINUTES', 120),
    'refresh_token_ttl_days' => (int) env('REFRESH_TOKEN_TTL_DAYS', 30),
];
