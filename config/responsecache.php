<?php

declare(strict_types=1);

return [
    'enabled' => env('RESPONSE_CACHE_ENABLED', true),
    'cache_profile' => App\Http\CacheProfiles\PublicApiCacheProfile::class,
    'cache_bypass_header' => [
        'name' => env('CACHE_BYPASS_HEADER_NAME', null),
        'value' => env('CACHE_BYPASS_HEADER_VALUE', null),
    ],
    'cache_lifetime_in_seconds' => 300,
    'add_cache_time_header' => env('APP_DEBUG', false),
    'cache_time_header_name' => env('RESPONSE_CACHE_HEADER_NAME', 'laravel-responsecache'),
    'add_cache_age_header' => env('RESPONSE_CACHE_AGE_HEADER', false),
    'cache_age_header_name' => env('RESPONSE_CACHE_AGE_HEADER_NAME', 'laravel-responsecache-age'),
    'cache_store' => env('RESPONSE_CACHE_DRIVER', 'redis'),
    'replacers' => [
        \Spatie\ResponseCache\Replacers\CsrfTokenReplacer::class,
    ],
    'cache_tag' => 'response-cache',
    'hasher' => \Spatie\ResponseCache\Hasher\DefaultHasher::class,
    'serializer' => \Spatie\ResponseCache\Serializers\DefaultSerializer::class,
];
