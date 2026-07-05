<?php

return [
    'hosts' => [
        [
            'host' => env('LOG_ELASTICSEARCH_HOST', 'localhost'),
            'port' => env('LOG_ELASTICSEARCH_PORT', 9200),
            'scheme' => env('LOG_ELASTICSEARCH_SCHEME', 'http'),
        ],
    ],

    'basicAuthentication' => [
        'username' => env('LOG_ELASTICSEARCH_USERNAME', ''),
        'password' => env('LOG_ELASTICSEARCH_PASSWORD', ''),
    ],

    'index_prefix' => env('LOG_ELASTICSEARCH_INDEX_PREFIX', 'app_logs'),

    // Number of ES replicas for the logs index. Use 0 only for single-node staging clusters.
    'replicas' => env('LOG_ELASTICSEARCH_REPLICAS', 0),

    /*
     * Elasticsearch Index Lifecycle Management is the default retention path for
     * new v3 indexes. Run `php artisan elastic-audit:lifecycle-policy` before
     * creating new HTTP/activity indexes so they pick up the rollover/delete
     * settings. The prune commands remain available when ILM is disabled, when you
     * need per-document retention_days, or as a manual cleanup fallback.
     */
    'lifecycle' => [
        'enabled' => env('LOG_ELASTICSEARCH_LIFECYCLE_ENABLED', true),
        'policy_name' => env('LOG_ELASTICSEARCH_LIFECYCLE_POLICY', strtolower(env('LOG_ELASTICSEARCH_INDEX_PREFIX', env('APP_NAME', 'app_logs'))).'_elastic_audit_policy'),
        'rollover_max_age' => env('LOG_ELASTICSEARCH_ROLLOVER_MAX_AGE', '30d'),
        'rollover_max_shard_size' => env('LOG_ELASTICSEARCH_ROLLOVER_MAX_SHARD_SIZE', '50gb'),
        'delete_after' => env('LOG_ELASTICSEARCH_LIFECYCLE_DELETE_AFTER', '360d'),
    ],
];
