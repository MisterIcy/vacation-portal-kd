<?php

require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables. A .env file is optional: in a deployment the platform sets them directly.
App\Shared\Environment::load(__DIR__ . '/..');

return
    [
        'paths' => [
            'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
            'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
        ],
        'environments' => [
            'default_migration_table' => 'phinxlog',
            'default_environment' => 'development',
            'development' => [
                'adapter' => 'mysql',
                'host' => $_ENV['DB_HOST'],
                'name' => $_ENV['DB_DATABASE'],
                // Migrations change the schema, which the application itself should not be allowed to do.
                // Use dedicated credentials when they are configured; otherwise the application's.
                'user' => $_ENV['DB_MIGRATION_USERNAME'] ?? $_ENV['DB_USERNAME'],
                'pass' => $_ENV['DB_MIGRATION_PASSWORD'] ?? $_ENV['DB_PASSWORD'],
                'port' => $_ENV['DB_PORT'],
                'charset' => $_ENV['DB_CHARSET'],
            ]
        ],
        'version_order' => 'creation'
    ];
