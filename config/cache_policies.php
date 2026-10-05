<?php

declare(strict_types=1);

return [
    'metadata' => [
        'ttl' => 3600,
        'tags' => ['metadata'],
    ],
    'bpt_top_players' => [
        'ttl' => 1800,
        'tags' => ['bpt'],
    ],
    'rankedin_upstream' => [
        'ttl' => 3600,
        'tags' => ['bpt', 'rankedin-upstream'],
    ],
    'templates_catalog' => [
        'ttl' => 86400,
        'tags' => ['templates'],
    ],
    'templates_metrics' => [
        'ttl' => 3600,
        'tags' => ['templates'],
    ],
    'reference_show' => [
        'ttl' => 3600,
        'tags' => ['reference'],
    ],
    'reference_index_picker' => [
        'ttl' => 300,
        'tags' => ['reference'],
    ],
    'event_scoped_index' => [
        'ttl' => 120,
        'tags' => [],
    ],
    'registration_queue_counts' => [
        'ttl' => 60,
        'tags' => ['mgr-list:event-registrations'],
    ],
    'event_show' => [
        'ttl' => 120,
        'tags' => [],
    ],
    'schedule_show' => [
        'ttl_live' => 30,
        'ttl_finalized' => 120,
        'tags' => [],
    ],
    'standings' => [
        'ttl' => 120,
        'tags' => [],
    ],
    'lottery_prizes_board' => [
        'ttl' => 300,
        'tags' => [],
    ],
    'app_events' => [
        'ttl' => 300,
        'tags' => ['response-cache'],
    ],
];
