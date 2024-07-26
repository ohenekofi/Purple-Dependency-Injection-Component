<?php

use Purple\Core\Container\Container;
use Purple\Libs\Harmony\Adapters\MySQLAdapter;
use Purple\Libs\Harmony\Adapters\PostgreSQLAdapter;
use Purple\Libs\Harmony\Adapters\SQLiteAdapter;
use Purple\Libs\Harmony\Events\EventDispatcher;
use Purple\Libs\Harmony\Factories\ConnectionFactory;
use Purple\Libs\Harmony\Factories\AdapterFactory;
use Purple\Libs\Harmony\Factories\QueryBuilderFactory;
use Purple\Libs\Harmony\QueryBuilders\MySQLQueryBuilder;
use Purple\Libs\Harmony\QueryBuilders\PostgreSQLQueryBuilder;
use Purple\Libs\Harmony\QueryBuilders\SQLiteQueryBuilder;
use Purple\Libs\Harmony\Harmony;
use Purple\Libs\Harmony\QueryExecutor;
use Purple\Libs\Harmony\Schema;
use Purple\Libs\Harmony\QueryBuilder;
use Purple\Libs\Harmony\MigrationManager;
use Purple\Libs\Harmony\Xpressive;
use Purple\Core\LoggingDecorator;
use Purple\Libs\Harmony\Factories\PlatformFactory;
use Purple\Libs\Harmony\Platform\MySQLPlatform;
use Purple\Libs\Harmony\QueryGenerator\SqlBuilder;
use Purple\Libs\Harmony\Interface\DatabasePlatform;
use Purple\Libs\Harmony\Interface\DatabaseAdapterInterface;
use Purple\Libs\Harmony\Interface\DatabaseBuilderInterface;
use Purple\Libs\Harmony\Blueprint;
use Purple\Libs\Harmony\TableDefinition;
use Purple\Libs\Harmony\ColumnDefinition;
use Purple\Core\Container\ContainerConfigurator;
use Purple\Core\Container\ContainerBuilder;
use Purple\Core\Services\Reference;
//marked for  delete
use Purple\Core\SomeService;
use Purple\Core\DbaseConnection;
use Purple\Core\Mailer;
use Purple\Core\FileLogger;
use Purple\Core\MiddlewareExamples\LoggingMiddlewares;
use Purple\Core\Services\Interface\MiddlewareInterface;
use Purple\Core\UserManager;

return function ($containerConfigurator) {
    $configPath = __DIR__ . '/../config/database.php';
 
    $services = $containerConfigurator->services();
    $middleware = $containerConfigurator->middlewares();
    $defaults = $containerConfigurator->defaults();

    //takes a string either hints to type hints autowire for all services by default or use annots for annotations 
    //$defaults->wireType("annots"); 
    //when its set to true, the container will use annotations along with wiretype
    //$defaults->setAnnotwire("annots"); 
    //when its set to true, the continue will use parameter hints for autowring along with wiretype
    //$defaults->setAutowire("annots"); 
    //by defaults all service have asGlobal false hence private, only alias makes them public or asGlobal method
    //by configuring this to true all methods become public by default
    //$defaults->setAsGlobal("annots"); 

    $services->parameters('db.host', 'localhost');
    $services->parameters('db.port', 3306);
    $services->parameters('db.username', 'root');
    $services->parameters('db.password', 'secret');
    $services->parameters('db.database', 'my_database');
    $services->parameters('db.charset', 'utf8');
    $services->parameters('db.options', []);
    $services->parameters('migrations_dir', '/path/to/migrations');
    $services->parameters('migrations_table', 'migrations');
    $services->parameters('column', 'default_column');
    $services->parameters('config.database_path', $configPath);
    $services->parameters('harmonyConfig', [
        'setting1' => 'value1',
        'setting2' => 'value2',
        // Other configuration parameters...
    ]);

    //internal autodirectory scanning and add to services definitions
    /*
    $containerConfigurator->discovery([
        'Purple\Core\Db\Example\\' => [
            'resource' => '../core/Db/Example/*',
            'exclude' => ['../core/Db/Example/{DependencyInjection,Entity,Migrations,Tests}']
        ]
    ]);
    */

    // Add middleware
    // Add global middleware
    //$middleware->addGlobalMiddleware(new LoggingMiddlewares());

    //for example use to be deleted 
    $services->parameters('session_data', "dfgfhgfgghjkhjkh");

    //defining event dispatcher
    $services->set('event_dispatcher', EventDispatcher::class) ->asGlobal(false)
    ->asShared(true);

    //define harmony service
    $services->set('harmony', Harmony::class)
        ->addArgument(new Reference('adapter'))
        ->addArgument(new Reference('sql_builder'))
        ->addArgument(new Reference('schema'))
        ->addArgument(new Reference('executor'))
        //->addArgument('%harmonyConfig%')
        ->addMethodCall('initialize', [])
        ->asGlobal(true)
        ->asShared(true);


    //defining adapter through factory
    // Define a service that uses a factory method to create an instance
    $services->set('adapter', AdapterFactory::class)
        ->factory([AdapterFactory::class, 'create'])
        ->asGlobal(false)
        ->asShared(true)
        //->addArgument('$DB_CONNECTION$')
        //->addArgument('$DB_DATABASE$')
        //->addArgument('$DB_HOST$')
        //->addArgument('$DB_USERNAME$')
        //->addArgument('$DB_PASSWORD$')
        //->addArgument('$DB_CHARSET$')
        ->implements(DatabaseAdapterInterface::class)
        ->autowire()
        ->asGlobal(false)
        ->asShared(true);

    //defining service sqlbuilder
    $services->set('sql_builder', QueryBuilderFactory::class)
        ->factory([QueryBuilderFactory::class, 'create'])
        //->addArgument(new Reference('adapter'))
        //->addArgument(new Reference('executor'))
        ->autowire()
        ->asGlobal(false)
        ->asShared(true);

    //defining platform
    $services->set('platform', PlatformFactory::class)
        ->factory([PlatformFactory::class, 'create'])
        //->addArgument('$DB_CONNECTION$')
        ->implements(DatabasePlatform::class) 
        ->asGlobal(false)
        ->asShared(true)
        ->autowire();

    // defining schema service
    $services->set('schema', Schema::class)
        ->addArgument(new Reference('adapter'))
        ->addArgument(new Reference('sql_builder'))
        ->addArgument(new Reference('event_dispatcher'))
        ->asGlobal(false)
        ->asShared(true);


    //defining executor
    $services->set('executor', QueryExecutor::class)
        ->addArgument(new Reference('adapter'))
        ->addArgument(new Reference('event_dispatcher'))
        ->asGlobal(true)
        ->asShared(true);

    //defining migration manager
    $services->set('migration_manager', MigrationManager::class)
        ->addArgument(new Reference('harmony'))
        ->addArgument('%migrations_dir%')
        ->addArgument('%migrations_table%')
        ->asGlobal(false)
        ->asShared(true);


    //defining xpressive fluent query builder 
    $services->set('xpressive', Xpressive::class)
    ->asGlobal(false)
    ->asShared(true)
    ->autowire();
    //->arguments(['@platform','@adapter','@executor']);

    
    //example use
    $services->set('mailer', Mailer::class)        
    ->asGlobal(false)
    ->asShared(true);

    // Define the decorator
    $services->set('decorMailer', LoggingDecorator::class)
     ->addArgument(new Reference('mailer'))
     ->decorate('mailer');
     

    $services->set('database', DbaseConnection::class)    ->asGlobal(false)
    ->asShared(true)->setAlias('database')->addTag(['example']);


    $services->set('logger', FileLogger::class)  
        ->addArgument(new Reference('mailer'))      
        ->asGlobal(true)
        ->asShared(true)
        ->lazy()
        ->addServiceMiddleware(new LoggingMiddlewares());

    $services->set('some_service', SomeService::class)->asGlobal(false)->lazy()
    ->asShared(true)->addMethodCall('doSomething',[]) ->addTag(['example']) ->autowire();

    $services->set('userManager', UserManager::class)
        ->asGlobal(true)
        ->asShared(true)
        ->addMethodCall('createUser',[])
        ->annotwire();


    // Define a service using an inline factory
    $services->set('database_connection', function ($container) {
        $host = $container->getParameter('db.host');
        $port = $container->getParameter('db.port');
        $username = $container->getParameter('db.username');
        $password = $container->getParameter('db.password');
        $database = $container->getParameter('db.database');

        return new MySQLPlatform();
    })
    ->asGlobal(true)
    ->asShared(true);

    // Finalize and detect circular dependencies
    $containerConfigurator->finalize();
   
};

/*
<?php
   // config/services.php

   return [
       'database_connection' => [
           'class' => 'MyApp\Database\Connection',
           'arguments' => [
               '%database.host%',
               '%database.username%',
               '%database.password%',
               '%database.database%',
           ],
       ],
       'cache' => [
           'class' => 'MyApp\Cache\CacheFactory',
           'factory' => ['MyApp\Cache\CacheFactory', 'create'],
           'arguments' => [
               '%cache.driver%',
               '%cache.host%',
           ],
       ],
       'api_client' => [
           'class' => 'MyApp\Api\Client',
           'arguments' => ['%api_endpoint%'],
       ],
       'logger' => [
           'class' => 'Monolog\Logger',
           'arguments' => [
               'app',
               [
                   new Reference('log_handler'),
               ],
           ],
       ],
       'log_handler' => [
           'class' => 'Monolog\Handler\StreamHandler',
           'arguments' => [
               '/var/log/myapp.log',
               '%log_level%',
           ],
       ],
   ];





   
*/