<?php

use App\Services\DatabaseService;
use App\Services\LoggerService;
use App\Services\UserService;
use App\Services\MailerFactory;
use App\Services\AbstractService;
use App\Services\ConcreteService;
use App\Services\ExpensiveService;
use App\Services\PrivateService;
use App\Contracts\ServiceInterface;
use App\Contracts\LoggerInterface;
use Purple\Libs\Harmony\Harmony;
use Purple\Libs\Harmony\Adapters\MySQLAdapter;
use Purple\Libs\Harmony\Adapters\PostgreSQLAdapter;
use Purple\Libs\Harmony\Adapters\SQLiteAdapter;
use Purple\Libs\Harmony\QueryBuilders\MySQLQueryBuilder;
use Purple\Libs\Harmony\QueryBuilders\PostgreSQLQueryBuilder;
use Purple\Libs\Harmony\QueryBuilders\SQLiteQueryBuilder;
use Purple\Libs\Harmony\Decorators\LoggingDecorator;
use Purple\Libs\Harmony\QueryBuilder;
use Purple\Libs\Harmony\QueryBuilderFactory;
use Purple\Libs\Harmony\Schema;
use Purple\Libs\Harmony\MigrationManager;
use Purple\Libs\Harmony\QueryExecutor;
use Purple\Libs\Harmony\Blueprint;
use Purple\Libs\Harmony\TableDefinition;
use Purple\Libs\Harmony\ColumnDefinition;
use Purple\Libs\Harmony\ConnectionFactory;
use Purple\Libs\Harmony\EventDispatcher;
use Purple\Libs\Harmony\AdapterFactory;
use Purple\Libs\Harmony\Interface\DatabaseAdapterInterface;
use Purple\Libs\Harmony\Interface\DatabaseBuilderInterface;

return [
    'parameters' => [
        'db.host' => 'localhost',
        'db.port' => 3306,
        'db.username' => 'root',
        'db.password' => 'secret',
        'db.database' => 'my_database',
        'db.charset' => 'utf8',
        'db.options' => [],
        'migrations_dir' => '/path/to/migrations',
        'migrations_table' => 'migrations',
        'column' => 'default_column',
        'config.database_path' => $configPath,
        'harmonyConfig' => [
            'setting1' => 'value1',
            'setting2' => 'value2',
            // Other configuration parameters...
        ],
    ],

    'services' => [
        'db' => [
            'class' => DatabaseService::class,
            'arguments' => [
                '%db.host%',
                '%db.name%',
                '%db.user%',
                '%db.password%',
            ],
        ],

        'logger' => [
            'class' => LoggerService::class,
            'arguments' => ['%logger.level%'],
            'calls' => [
                ['setFilePath', ['%kernel.logs_dir%/app.log']],
            ],
        ],

        'user_service' => [
            'class' => UserService::class,
            'arguments' => ['@db', '@logger'],
            'lazy' => true,
        ],

        'mailer' => [
            'factory' => ['App\Services\MailerFactory', 'create'],
            'alias' => 'app.mailer',
        ],

        'abstract_service' => [
            'class' => AbstractService::class,
            'extends' => 'logger',
            'factory' => ['@logger', 'createAbstractService'],
        ],

        'interface_service' => [
            'class' => ConcreteService::class,
            'interfaces' => [
                ServiceInterface::class => 'interface_service',
            ],
        ],

        'method_lazy_service' => [
            'class' => ExpensiveService::class,
            'lazy' => 'method',
            'lazy_method' => 'initialize',
        ],

        'property_lazy_service' => [
            'class' => ExpensiveService::class,
            'lazy' => 'property',
            'lazy_properties' => ['expensiveProperty'],
        ],

        'private_service' => [
            'class' => PrivateService::class,
            'visibility' => 'private',
        ],

        'base_adapter' => [
            'class' => MySQLAdapter::class,
            'arguments' => ['%config.database_path%'],
            'scope' => 'singleton',
            'visibility' => 'public',
        ],

        'event_dispatcher' => [
            'class' => EventDispatcher::class,
        ],

        'connection_factory' => [
            'class' => ConnectionFactory::class,
            'arguments' => ['%config.database_path%'],
        ],

        'connection_and_adapter' => [
            'factory' => ['@connection_factory', 'create'],
        ],

        'adapter_factory' => [
            'class' => AdapterFactory::class,
            'arguments' => ['%config.database_path%'],
        ],

        'adapter' => [
            'factory' => ['@adapter_factory', 'create'],
            'arguments' => ['%config.database_path%'],
        ],

        'mysql_adapter' => [
            'extends' => 'base_adapter',
            'scope' => 'singleton',
        ],

        'pgsql_adapter' => [
            'class' => PostgreSQLAdapter::class,
        ],

        'sqlite_adapter' => [
            'class' => SQLiteAdapter::class,
            'arguments' => ['%db.database%'],
        ],

        'mysql_query_builder' => [
            'class' => MySQLQueryBuilder::class,
            'arguments' => ['@mysql_adapter', '@query_executor'],
        ],

        'pgsql_query_builder' => [
            'class' => PostgreSQLQueryBuilder::class,
            'arguments' => ['@pgsql_adapter'],
        ],

        'sqlite_query_builder' => [
            'class' => SQLiteQueryBuilder::class,
            'arguments' => ['@sqlite_adapter'],
        ],

        'harmony' => [
            'class' => Harmony::class,
            'arguments' => ['@mysql_adapter', '@mysql_query_builder', '@schema', '@query_executor', '@container', '%harmonyConfig%'],
            'decorators' => [
                LoggingDecorator::class,
            ],
            'scope' => 'singleton',
            'alias' => 'harmony',
            'calls' => [
                ['initialize', []],
            ],
        ],

        'query_builder' => [
            'class' => QueryBuilder::class,
            'factory' => ['@concrete_query_builder_factory', 'create'],
            'scope' => 'prototype',
            'alias' => 'qb',
            'visibility' => 'public',
        ],

        'concrete_query_builder_factory' => [
            'class' => QueryBuilderFactory::class,
            'arguments' => ['@adapter', '@query_executor'],
        ],

        'schema' => [
            'class' => Schema::class,
            'arguments' => ['@adapter', '@query_builder', '@event_dispatcher'],
            'scope' => 'singleton',
            'alias' => 'db_schema',
            'visibility' => 'public',
        ],

        'migration_manager' => [
            'class' => MigrationManager::class,
            'arguments' => ['@harmony', '%migrations_dir%', '%migrations_table%'],
        ],

        'query_executor' => [
            'class' => QueryExecutor::class,
            'arguments' => ['@mysql_adapter', '@event_dispatcher'],
        ],

        'blueprint' => [
            'class' => Blueprint::class,
        ],

        'table_definition' => [
            'class' => TableDefinition::class,
        ],

        'column_definition' => [
            'class' => ColumnDefinition::class,
            'arguments' => ['%column']
        ],
        'container' => [
            'factory' => function () use (&$container) {
                return $container;
            },
            'scope' => 'singleton',
        ],
    ],
    
    'interfaces' => [
        DatabaseAdapterInterface::class => 'mysql_adapter',
        DatabaseBuilderInterface::class => 'query_builder',
    ],
]

?>
    This updated example includes the following enhancements:

1. **Imported Namespaces**: The relevant namespaces are imported for better readability and organization.
2. **Harmony-Related Services**: Services related to the Harmony ORM are included, such as adapters, query builders, schema, migration manager, query executor, and various utility classes.
3. **Service Decorators**: The `harmony` service has a `decorators` key that specifies the `LoggingDecorator` class to be applied.
4. **Container Service**: A service named `container` is defined, which returns the container instance itself using a factory function.
5. **Interfaces Mapping**: The `interfaces` section maps the `DatabaseAdapterInterface` and `DatabaseBuilderInterface` interfaces to their corresponding service implementations.

