<?php

declare(strict_types=1);

return [
    'base_url' => rtrim((string) env('RANKEDIN_BASE_URL', 'https://api.rankedin.com'), '/'),
    'organisation_id' => (int) env('RANKEDIN_BPT_ORGANISATION_ID', 11317),
    'ranking_id' => (int) env('RANKEDIN_BPT_RANKING_ID', 15792),
    'men_ranking_type' => (int) env('RANKEDIN_BPT_MEN_RANKING_TYPE', 3),
    'men_age_group' => (int) env('RANKEDIN_BPT_MEN_AGE_GROUP', 82),
    'women_ranking_type' => (int) env('RANKEDIN_BPT_WOMEN_RANKING_TYPE', 4),
    'women_age_group' => (int) env('RANKEDIN_BPT_WOMEN_AGE_GROUP', 83),
    'max_concurrent_requests' => max(1, (int) env('RANKEDIN_BPT_MAX_CONCURRENT_REQUESTS', 6)),
    'default_take' => min(100, max(1, (int) env('RANKEDIN_BPT_DEFAULT_TAKE', 20))),
    'max_take' => min(500, max(1, (int) env('RANKEDIN_BPT_MAX_TAKE', 100))),
    'organisation_events_page_size' => min(100, max(1, (int) env('RANKEDIN_BPT_ORG_EVENTS_PAGE_SIZE', 50))),
    'rankings_page_size' => min(200, max(1, (int) env('RANKEDIN_BPT_RANKINGS_PAGE_SIZE', 100))),
    'http_timeout_seconds' => max(5, (int) env('RANKEDIN_BPT_HTTP_TIMEOUT', 45)),
];
