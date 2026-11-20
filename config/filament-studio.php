<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Table Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix used for all database tables created by Filament Studio.
    | Change this if you need to avoid conflicts with existing tables.
    |
    */
    'table_prefix' => 'studio_',

    /*
    |--------------------------------------------------------------------------
    | REST API
    |--------------------------------------------------------------------------
    |
    | Configure the auto-generated REST API for Studio collections.
    |
    */
    'api' => [
        'enabled' => env('STUDIO_API_ENABLED', false),
        'prefix' => env('STUDIO_API_PREFIX', 'api/studio'),
        'rate_limit' => env('STUDIO_API_RATE_LIMIT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    |
    | Configure how Studio registers permissions with spatie/laravel-permission.
    | Set 'auto_register' to false to prevent automatic permission seeding.
    | Set 'guard' to specify which auth guard the permissions belong to.
    | 'auto_grant_roles' / 'auto_grant_to_field_managers' control which roles
    | automatically receive record access to newly created collections.
    |
    */
    'permissions' => [
        'auto_register' => env('STUDIO_PERMISSIONS_AUTO_REGISTER', true),
        'guard' => env('STUDIO_PERMISSIONS_GUARD'),

        // When a collection is created (wizard, MCP, seeder or plain code) its per-collection
        // record permissions are granted to these existing roles, e.g. ['super_admin', 'admin'].
        // Roles are never created; names that do not exist are ignored.
        'auto_grant_roles' => array_values(array_filter(array_map('trim', explode(',', (string) env('STUDIO_PERMISSIONS_AUTO_GRANT_ROLES', ''))))),

        // Also grant new collections to every role that holds `studio.manageFields`.
        'auto_grant_to_field_managers' => env('STUDIO_PERMISSIONS_AUTO_GRANT_TO_FIELD_MANAGERS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Multilingual
    |--------------------------------------------------------------------------
    |
    | Configure multilingual support for Studio collections.
    | When enabled, collections can opt into per-locale record values.
    |
    */
    'locales' => [
        'enabled' => env('STUDIO_LOCALES_ENABLED', false),
        'available' => ['en'],
        'default' => 'en',
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Search
    |--------------------------------------------------------------------------
    |
    | Studio records appear in the panel's global search, matched on their text
    | fields, for every collection the user may view.
    |
    */
    'global_search' => [
        'enabled' => env('STUDIO_GLOBAL_SEARCH_ENABLED', true),
        'limit_per_collection' => env('STUDIO_GLOBAL_SEARCH_LIMIT_PER_COLLECTION', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP (Model Context Protocol)
    |--------------------------------------------------------------------------
    |
    | Configure the AI-agent MCP server. Disabled by default. When enabled,
    | the package registers a server over HTTP/SSE (mounted at the configured
    | prefix) and/or stdio (via `php artisan mcp:start <handle>`). All access
    | is authenticated via StudioApiKey. See docs/superpowers/specs/
    | 2026-05-09-filament-studio-mcp-server-design.md.
    |
    */
    'mcp' => [
        'enabled' => env('STUDIO_MCP_ENABLED', false),

        'http' => [
            'enabled' => env('STUDIO_MCP_HTTP_ENABLED', true),
            'prefix' => env('STUDIO_MCP_HTTP_PREFIX', 'ai/studio'),
            'rate_limit' => env('STUDIO_MCP_HTTP_RATE_LIMIT', 120),
        ],

        'stdio' => [
            'enabled' => env('STUDIO_MCP_STDIO_ENABLED', true),
            'handle' => env('STUDIO_MCP_STDIO_HANDLE', 'studio'),
        ],

        'confirm_token_ttl' => env('STUDIO_MCP_CONFIRM_TOKEN_TTL', 300),

        'limits' => [
            'query_max_per_page' => env('STUDIO_MCP_QUERY_MAX_PER_PAGE', 100),
            'query_max_filter_depth' => env('STUDIO_MCP_QUERY_MAX_FILTER_DEPTH', 5),
            'create_collection_max_fields' => env('STUDIO_MCP_CREATE_COLLECTION_MAX_FIELDS', 50),
        ],

        'logging' => [
            'channel' => env('STUDIO_MCP_LOG_CHANNEL', 'stack'),
            'log_requests' => env('STUDIO_MCP_LOG_REQUESTS', true),
            'log_errors' => env('STUDIO_MCP_LOG_ERRORS', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Flows
    |--------------------------------------------------------------------------
    |
    | Configure the Flows system for workflow automation, queueing, and logging.
    |
    */
    'flows' => [
        'enabled' => env('STUDIO_FLOWS_ENABLED', false),
        'queue' => env('STUDIO_FLOWS_QUEUE', 'default'),
        'connection' => env('STUDIO_FLOWS_CONNECTION'),
        'log_channel' => env('STUDIO_FLOWS_LOG_CHANNEL', 'daily'),
        'log_retention_days' => (int) env('STUDIO_FLOWS_LOG_RETENTION_DAYS', 30),
        'max_call_depth' => (int) env('STUDIO_FLOWS_MAX_CALL_DEPTH', 5),
        'webhook_rate_limit_per_minute' => env('STUDIO_FLOWS_WEBHOOK_RATE_LIMIT', 60),
        'webhook_timestamp_window_seconds' => env('STUDIO_FLOWS_WEBHOOK_TS_WINDOW', 300),
        'webhook_ip_allowlist' => [],
        'sensitive_key_patterns' => [
            '/password/i',
            '/token/i',
            '/secret/i',
            '/api[_-]?key/i',
            '/authorization/i',
            '/bearer/i',
        ],
        'export' => [
            'max_range_days' => (int) env('STUDIO_FLOWS_EXPORT_MAX_RANGE_DAYS', 30),
        ],
    ],
];
