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
            ],
            // A throwaway database for the integration tests (`composer db:migrate:test`). It is
            // configured separately so that nothing here can ever point at the development data.
            'testing' => [
                'adapter' => 'mysql',
                'host' => $_ENV['TEST_DB_HOST'] ?? getenv('TEST_DB_HOST') ?: 'localhost',
                // `db:verify-migrations` rolls this database back completely, so it must be a test database.
                'name' => App\Shared\TestDatabaseName::assertSafe(
                    $_ENV['TEST_DB_DATABASE'] ?? getenv('TEST_DB_DATABASE') ?: App\Shared\TestDatabaseName::DEFAULT,
                ),
                'user' => $_ENV['TEST_DB_USERNAME'] ?? getenv('TEST_DB_USERNAME') ?: 'vacation_test',
                'pass' => $_ENV['TEST_DB_PASSWORD'] ?? getenv('TEST_DB_PASSWORD') ?: '',
                'port' => $_ENV['TEST_DB_PORT'] ?? getenv('TEST_DB_PORT') ?: '3306',
                'charset' => 'utf8mb4',
            ],
        ],
        'version_order' => 'creation'
    ];
