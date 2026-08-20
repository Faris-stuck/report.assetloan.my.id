<?php

use Illuminate\Support\Str;

return [
    'default' => env('CACHE_STORE', 'failover'),

    // Keep rate-limit counters in Redis so quotas are shared across workers.
    'limiter' => env('CACHE_LIMITER', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers. You may even define multiple stores for the
    | same cache driver to group types of items stored in your caches.
    |
    | Supported drivers: "array", "database", "file", "memcached",
    |                    "redis", "dynamodb", "octane", "null"
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],

        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => env('DYNAMODB_ENDPOINT'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing the APC, database, memcached, Redis, and DynamoDB cache
    | stores, there might be other applications using the same cache. For
    | that reason, you may prefix every cache key to avoid collisions.
    |
    */

    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_cache_'),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL Strategy Per Query Type
    |--------------------------------------------------------------------------
    |
    | Define Time To Live (TTL) values in seconds for different types of queries.
    | Master data has longer TTL (rarely changes), while frequently-accessed 
    | data has shorter TTL to maintain freshness.
    |
    | Master Data (24 hours): Damage categories, locations, violation types
    | User Data (30 min - 1 hour): User profiles, user lists
    | Report Data (30 min - 1 hour): Reports, statistics
    | Short-lived (1-5 min): Rate limiting, sessions
    |
    */

    'ttl' => [
        // Master data - rarely changes, longer TTL
        'damage_categories' => 86400,      // 24 hours
        'locations' => 86400,              // 24 hours
        'violation_types' => 86400,        // 24 hours
        'school_classes' => 43200,         // 12 hours
        'subjects' => 43200,               // 12 hours
        'staff_units' => 43200,            // 12 hours

        // User data - moderate changes
        'user_profile' => 1800,            // 30 minutes
        'user_list' => 3600,               // 1 hour
        'user_by_role' => 3600,            // 1 hour
        'homeroom_classes' => 3600,        // 1 hour

        // Report data - frequent changes
        'report_list' => 3600,             // 1 hour
        'report_detail' => 1800,           // 30 minutes
        'report_by_student' => 1800,       // 30 minutes
        'report_by_location' => 3600,      // 1 hour
        'report_statistics' => 1800,       // 30 minutes
        'report_status_history' => 1800,   // 30 minutes

        // Attachment data
        'attachment_list' => 1800,         // 30 minutes
        'attachment_detail' => 900,        // 15 minutes

        // Rate limiting & session - short lived
        'rate_limit' => 60,                // 1 minute
        'session' => 7200,                 // 2 hours (SESSION_LIFETIME)
        'api_request' => 300,              // 5 minutes
    ],

];


