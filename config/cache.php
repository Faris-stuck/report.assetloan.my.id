<?php

use Illuminate\Support\Str;

return [
    'default' => env('CACHE_STORE', 'failover'),
    'limiter' => env('CACHE_LIMITER', 'redis'),
    'stores' => [
        'array' => ['driver' => 'array', 'serialize' => false],
        'database' => ['driver'=>'database','connection'=>env('DB_CACHE_CONNECTION'),'table'=>env('DB_CACHE_TABLE','cache'),'lock_connection'=>env('DB_CACHE_LOCK_CONNECTION'),'lock_table'=>env('DB_CACHE_LOCK_TABLE')],
        'file' => ['driver'=>'file','path'=>storage_path('framework/cache/data'),'lock_path'=>storage_path('framework/cache/data')],
        'memcached' => ['driver'=>'memcached','persistent_id'=>env('MEMCACHED_PERSISTENT_ID'),'sasl'=>[env('MEMCACHED_USERNAME'),env('MEMCACHED_PASSWORD')],'options'=>[],'servers'=>[['host'=>env('MEMCACHED_HOST','127.0.0.1'),'port'=>env('MEMCACHED_PORT',11211),'weight'=>100]]],
        'redis' => ['driver'=>'redis','connection'=>env('REDIS_CACHE_CONNECTION','cache'),'lock_connection'=>env('REDIS_CACHE_LOCK_CONNECTION','default')],
        'dynamodb' => ['driver'=>'dynamodb','key'=>env('AWS_ACCESS_KEY_ID'),'secret'=>env('AWS_SECRET_ACCESS_KEY'),'region'=>env('AWS_DEFAULT_REGION','us-east-1'),'table'=>env('DYNAMODB_CACHE_TABLE','cache'),'endpoint'=>env('DYNAMODB_ENDPOINT')],
        'octane' => ['driver'=>'octane'],
    ],
    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME','laravel'),'_').'_cache_'),
    'ttl' => ['damage_categories'=>86400,'locations'=>86400,'violation_types'=>86400,'school_classes'=>43200,'subjects'=>43200,'staff_units'=>43200,'user_profile'=>1800,'user_list'=>3600,'user_by_role'=>3600,'homeroom_classes'=>3600,'report_list'=>3600,'report_detail'=>1800,'report_by_student'=>1800,'report_by_location'=>3600,'report_statistics'=>1800,'report_status_history'=>1800,'attachment_list'=>1800,'attachment_detail'=>900,'rate_limit'=>60,'session'=>7200,'api_request'=>300],
];
