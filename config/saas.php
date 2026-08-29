<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Schema
    |--------------------------------------------------------------------------
    */

    'platform_schema' => env('DB_SCHEMA', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Tenant Schema
    |--------------------------------------------------------------------------
    |
    | Tenant schemas are generated from a controlled identifier.
    | Never use raw user input as a PostgreSQL schema name.
    |
    */

    'tenant' => [

        'schema_prefix' => env('TENANT_SCHEMA_PREFIX', 'tenant_'),

        'schema_name_max_length' => 63,

    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Provisioning
    |--------------------------------------------------------------------------
    */

    'provisioning' => [

        'enabled' => env('TENANT_PROVISIONING_ENABLED', true),

    ],

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    */

    'api' => [

        'version' => env('API_VERSION', 'v1'),

    ],

];
