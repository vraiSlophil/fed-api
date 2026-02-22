<?php

return [
    'default_page' => (int) env('API_PAGINATION_DEFAULT_PAGE', 1),
    'default_per_page' => (int) env('API_PAGINATION_DEFAULT_PER_PAGE', 15),
    'max_per_page' => (int) env('API_PAGINATION_MAX_PER_PAGE', 100),
];
