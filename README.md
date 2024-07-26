# Purple Framework Documentation

## Table of Contents
1. [Introduction](#introduction)
2. [Core Components](#core-components)
3. [Dependency Injection Container](#dependency-injection-container)
4. [Kernel](#kernel)
5. [Service Management](#service-management)
6. [Event System](#event-system)
7. [Kernel Extensions](#kernel-extensions)
8. [Configuration Management](#configuration-management)
9. [Caching](#caching)
10. [Service Discovery](#service-discovery)
11. [Compiler Passes](#compiler-passes)
12. [Error Handling](#error-handling)

## Introduction
Purple is a comprehensive PHP framework designed to provide a robust foundation for building scalable and maintainable applications. It features a powerful dependency injection container, modular architecture, and various tools to enhance development productivity. Inspired by popular frameworks like Symfony but with its own unique features. It provides a flexible and extensible way to manage dependencies and services in PHP applications. NOTE STILL UNDER DEVELOPMENT BUT CAN BE USED IN APPLLICATIONS. 

## Installation

You can install PHP Container via Composer. Run the following command in your terminal:

```
composer require purpleharmonie/dependency-injection
```

## Core Components

### Dependency Injection Container
The heart of the Purple framework, managing service instantiation and dependencies.

Features:
- Service registration and retrieval
- Autowiring
- Lazy loading
- Scoped services (asGlobal, asShared)
- Aliasing
- Tagging
- Service decoration
- Middleware 

### Kernel
Manages the application's lifecycle and bootstraps the framework.

Features:
- Environment management
- Debug mode
- Bundle registration and booting
  in using bundle registration, your set the $configDir path. the bunlde classes whould be organised in an array
  and with each class extending the bundle interface. Find an example bundles in the examples folder
  this is to cretae the bundle registry in the $config path. It loaded into services during kernel boot
  ```php
  <?php
    // config/bundles.php
    return [
        Purple\Core\Services\Bundles\ExampleBundle::class,
    ];
  ```
- Container initialization
- Error handler setup

## Service Management

### Service Registration
- `set($id, $class)`: Register a service
- `alias($alias, $service)`: Create an alias for a service
- `addTag($id, $attributes)`: Tag a service
- `Configuring service visibility:` (asGlobal, asShared)
- `asGlobal(true)` / `asGlobal(true)`: Set service visibility. false by default 
- `asShared(true)`: Mark a service as shared (singleton)
- `setLazy(true)`: Enable/disable lazy loading for a service

### Service Configuration
- `addArgument($id, $argument)`: Add an argument to a service. 
- `arguments($id, array $arguments)`: Set all arguments for a service
- `addMethodCall($id, $method, $arguments)`: Add a method call to a service
- `factory($id, $factory)`: Set a factory for a service. inline factorie are not cached and also only php not yaml
- `implements($name, $interface)`: Specify an interface implementation
- `extends($id, $abstract)`: Set a service to extend another
- `autowire($id)`: Enable autowiring for a service using type hints
- `annotwire($id)`: Enable autowiring for a service using annotation

```php

<?php
//services.php
return function ($containerConfigurator) {
    $services = $containerConfigurator->services();

    //define harmony service with argument
    $services->set('harmony', Harmony::class)
        ->addArgument(new Reference('adapter'))
        ->addArgument(new Reference('sql_builder'))
        ->addArgument(new Reference('schema'))
        ->addArgument(new Reference('executor'))
        //->addArgument('%harmonyConfig%')
        ->addMethodCall('initialize', [/*pass array of arguments*/]) //[@service,'%DB_ENV%', %PARAMETER%]
        ->asGlobal(true)
        ->asShared(true);

    // Define a service using an inline factory
    $services->set('database_connection', function ($container) {
        $host = $container->getParameter('db.host');
        $port = $container->getParameter('db.port');
        $username = $container->getParameter('db.username');
        $password = $container->getParameter('db.password');
        $database = $container->getParameter('db.database');

        return new DatabaseConnection($host, $port, $username, $password, $database);
    })
    ->asGlobal(true)
    ->asShared(true);

    // Define a service using an inline factory with dependencies
    $services->set('user_repository', function ($container) {
        $dbConnection = $container->get('database_connection');
        return new UserRepository($dbConnection);
    })
    ->asGlobal(false)
    ->asShared(true);

    // You can also use arrow functions (PHP 7.4+) for more concise definitions
    $services->set('logger', fn($container) => new Logger($container->getParameter('log_file')))
        ->asGlobal(true) //available within and outiside the container 
        ->asShared(true); //either a shared instance or new instance per request


    //Defining factory with classes . 
    $services->set('platform', PlatformFactory::class)
        ->factory([PlatformFactory::class, 'create']) // Factory class name and method that returns the service
        //->addArgument('$DB_CONNECTION$')
        ->implements(DatabasePlatform::class) // handles the interface
        ->asGlobal(false)
        ->asShared(true)
        ->autowire();

    $services->set('some_service', SomeService::class)
        ->asGlobal(false)->lazy()
        ->asShared(true)
        ->addMethodCall('doSomething',[]) // since the method is defined with arguments passed. it will be autorired. cos of autowire set on this service
        ->addTag(['example'])
        ->autowire(); //will use parameter type hinting to resolve the class

    //with automatically autowire class and specified method using annotations. 
    $services->set('userManager', UserManager::class)
        ->asGlobal(true)
        ->asShared(true)
        ->addMethodCall('createUser',[])
        ->annotwire();
    
    //arguments setting in bulk @service , env param %HOST% or passed param %parameter%
    $services->set('xpressive', Xpressive::class)
    ->asGlobal(false)
    ->asShared(true)
    ->autowire();
    ->arguments(['@platform','@adapter','@executor']);
};
```

### New Methods
- `bindIf($abstract, $concrete)`: Conditionally bind a service
- `callable($abstract, callable $factory)`: Register a service with a callable factory

## Event System
Allows hooking into various points of the application lifecycle.

Features:
- Event dispatching
- Listener registration with priorities

```php
//event dispatcher boot
$eventDispatcher->addListener('kernel.pre_boot', function() {
    echo "Kernel is about to boot!\n";
});
$eventDispatcher->addListener('kernel.post_boot', function() {
    echo "Kernel has finished booting!\n";
});
```

## Kernel Extensions
Modular way to extend and configure the kernel and container.

Features:
- Extension registration
- Container configuration through extensions

```php
$kernel->addExtension(new DatabaseExtension());

<?php

namespace Purple\Core\EventsExamples;

use Purple\Core\Services\Container;
use Purple\Core\Services\Interface\KernelExtensionInterface;

// Example implementation
class DatabaseExtension implements KernelExtensionInterface
{
    public function load(Container $container): void
    {
        $container->set('database', function(Container $c) {
            return new DatabaseConnection(
                $c->getParameter('db_host'),
                $c->getParameter('db_name'),
                $c->getParameter('db_user'),
                $c->getParameter('db_pass')
            );
        });
    }
}

//the extension interface that extensions must implement 
<?php

namespace Purple\Core\Services\Interface;

use Purple\Core\Services\Container;

interface KernelExtensionInterface
{
    public function load(Container $container): void;
}

```

## Configuration Management
- Environment-specific configurations
- Parameter management
- Configuration file loading (YAML, PHP)
- Support for .env files
- Programmatic service definition

```php
// Load environment variables
$kernel->loadEnv(__DIR__ . '/../.env');

//either one or both 
// Load service configurations from a file (e.g., YAML)
$kernel->loadConfigurationFromFile(__DIR__ . '/../config/services.yaml');

// Define services in PHP 
$kernel->loadConfigurationFromFile(__DIR__ . '/../public/service.php');
```

## Caching
- Service graph caching
- Cached container compilation
- Configurable cache type (Redis, file, memory)
- Cache size and eviction policy settings

## Service Discovery
Automatic discovery and registration of services based on directory structure and namespaces.

```php
//using type hinting 
$kernel->autoDiscoverServices('../core/Db/Example', 'Purple\Core\Db\Example');

using annotations 
$container->annotationDiscovery([
    'namespace' => [
        'Purple\\Core\\AnnotEx' => [
            'resource' => __DIR__.'/../core/AnnotEx',
            'exclude' => []
        ]
    ]
]);

//services file
 //internal autodirectory scanning and add to services definitions

$containerConfigurator->discovery([
    'Purple\Core\Db\Example\\' => [
        'resource' => '../core/Db/Example/*',
        'exclude' => ['../core/Db/Example/{DependencyInjection,Entity,Migrations,Tests}']
    ]
]);
    
   
```
```markdown
//yaml services
discovery:
  App\Directory\:
    resource: '../core/Db/Example/*'
    exclude: '../core/Db/Example/{DependencyInjection,Entity,Migrations,Tests}' 
```


## Service Tracking 
Monitors service usage and implements basic garbage collection:
- Tracks service usage frequency and last usage time
- Implements a configurable garbage collection mechanism

## Compiler Passes
Custom logic for modifying container configuration before it's compiled.
view usage examples below and the examples folder 

Features:
- Priority-based execution
- Access to container for modifications

## Error Handling
Customizable error handling and logging system.

---

This documentation provides an overview of the main features and components of the Purple framework. For detailed usage instructions and advanced configurations, please refer to the specific component documentation or code examples.







# USAGE EXAMPLES

## EventDispatcherInterface:

Defines methods for dispatching events and adding listeners.
The example implementation allows adding listeners with priorities and dispatching events to all registered listeners.


## KernelExtensionInterface:

Defines a load method that extensions use to configure the container.
The example DatabaseExtension shows how you might set up a database connection service.


## bindIf() usage:

Allows setting a default implementation that won't be overwritten if already defined.
In the example, we set a FileLogger as the default, and a subsequent attempt to bind a ConsoleLogger doesn't overwrite it.


## callable() usage:

Allows defining a service using a callable factory.
In the example, we define a Mailer service that depends on other services from the container.

```php
// Assuming we have a Container instance
$container = new Container(/* ... */);

// Using bindIf()
$container->bindIf('logger', function(Container $c) {
    return new FileLogger($c->getParameter('log_file'));
});

// This won't overwrite the existing 'logger' binding
$container->bindIf('logger', function(Container $c) {
    return new ConsoleLogger();
});

// Using callable()
$container->callable('mailer', function(Container $c) {
    $transport = $c->get('mailer.transport');
    $logger = $c->get('logger');
    return new Mailer($transport, $logger);
});

// Later in the code, you can get these services
$logger = $container->get('logger'); // This will be a FileLogger

```

## Decorator Usage

```php
//original use
$services->set('mailer', Mailer::class);

// Define the decorator
$services->set('decorMailer', LoggingDecorator::class)
    ->addArgument(new Reference('mailer'))
    ->decorate('mailer');

//or alternatively 
$services->set('decorMailer', LoggingDecorator::class)
    ->addArgument(new Reference('mailer'));
```

## Alias Usage
The setAlias() method in Container now automatically sets the alias as public.
```php
// Using setAlias
$container->setAlias('app.database', DatabaseConnection::class);

// Binding interfaces to concrete implementations
$container->set('interfaceservicename', ConcreteUserRepositoryInterface::class);
$container->set('concreteclassforinterfaceservicename', ConcreteUserRepository::class);
$container->setAlias('interfaceservicename', 'concreteclassforinterfaceservicename');

// Quick alias chaining
$services->set('mailer', Mailer::class)->alias('public_mailer_service');

// Using aliases in service definitions
$container->set('another_service', AnotherService::class)
    ->addArgument(new Reference('app.user_repository'));

// Retrieving services using aliases
$dbConnection = $container->get('app.database');
$userRepo = $container->get(UserRepositoryInterface::class);
$mailer = $container->get('public_mailer_service'); 
```

## Middleware Usage 
```php
// In your configuration
$container = new Container();
$configurator = new ContainerConfigurator($container);

// Add global middleware
$configurator->addGlobalMiddleware(new LoggingMiddleware());
$configurator->addGlobalMiddleware(new ProfilingMiddleware());

// Configure a service with specific middleware
$configurator->set('user_service', UserService::class)
    ->addServiceMiddleware(new ValidationMiddleware());

// Usage
$userService = $container->get('user_service');
// This will log creation, validate the service, and profile creation time
```
middleware interface 
```php
<?php
namespace Purple\Core\Services\Interface;

use Closure;

interface MiddlewareInterface
{
    public function process($service, string $id, Closure $next);
}

```

## Annotation Directory Scanner Usage 
```php
// Usage example outside service files or index
$container = new Container();

$container->annotationDiscovery([
    'namespace' => [
        'App\\Core\\Db\\Example' => [
            'resource' => '../core/Db/Example/*',
            'exclude' => ['../core/Db/Example/{DependencyInjection,Entity,Migrations,Tests}']
        ]
    ]
]);
```
```php
// example of annotation classes with contructor and method inject
<?php
namespace Purple\Core;

use Purple\Core\FileLogger;
use Purple\Core\Mailer;
use Purple\Core\SomeService;

#[Service(name: "userManager")]
class UserManager {
    private FileLogger $logger;

    
    public function __construct(#[Inject("@logger")] FileLogger $logger, $basic ="ghost") {
        $this->logger = $logger;
    }


    public function createUser(#[Inject("@mailer")] Mailer $mailer,#[Inject("@some_service")] SomeService $some_service): bool {
        echo "anot method is called ";
        return false;
    }
}

//example with property inject 
class UserManager {
    #[Inject("@logger")]
    private FileLogger $logger;

    #[Inject("@mailer")]
    private Mailer $mailer;

    public function __construct(#[Inject("@database")] Database $db) {
        $this->db = $db;
    }

    // ... other methods ...
}
```
## General examples

```php
//services.php 

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

    $containerConfigurator->discovery([
        'Purple\Core\Db\Example\\' => [
            'resource' => '../core/Db/Example/*',
            'exclude' => ['../core/Db/Example/{DependencyInjection,Entity,Migrations,Tests}']
        ]
    ]);

    // Add middleware
    // Add global middleware
    $middleware->addGlobalMiddleware(new LoggingMiddlewares());

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

    // Finalize and detect circular dependencies
    $containerConfigurator->finalize();
};

```
```php

// services.yaml
    _defaults:
    setAsGlobal: true      
    setAutowire: false
    setAnnotwire: false
    wireType: hints
    addGlobalMiddleware: Purple\Core\MiddlewareExamples\LoggingMiddlewares

    parameters:
    db.host: '%DB_HOST%'
    db.port: '%DB_PORT%'
    db.username: '%DB_USERNAME%'
    db.password: '%DB_PASSWORD%'
    db.database: '%DB_DATABASE%'
    db.charset: '%DB_CHARSET%'
    db.options: []
    migrations_dir: '/path/to/migrations'
    migrations_table: 'migrations'
    column: 'default_column'
    config.database_path: '%config.database_path%'
    harmonyConfig:
        setting1: 'value1'
        setting2: 'value2'

    services:
    event_dispatcher:
        class: Purple\Libs\Harmony\Events\EventDispatcher

    harmony:
        class: Purple\Libs\Harmony\Harmony
        arguments:
        - '@adapter'
        - '@sql_builder'
        - '@schema'
        - '@executor'
        method_calls:
        - [initialize, []]
        asGlobal: true
        asShared: true  

    adapter:
        factory: [Purple\Libs\Harmony\Factories\AdapterFactory, create]
        arguments:
        - '%DB_CONNECTION%'
        - '%DB_DATABASE%'
        - '%DB_HOST%'
        - '%DB_USERNAME%'
        - '%DB_PASSWORD%'
        - '%DB_CHARSET%'
        implements: Purple\Libs\Harmony\Interface\DatabaseAdapterInterface

    sql_builder:
        factory: [Purple\Libs\Harmony\Factories\QueryBuilderFactory, create]
        arguments:
        - '@adapter'
        - '@executor'

    platform:
        factory: [Purple\Libs\Harmony\Factories\PlatformFactory, create]
        arguments:
        - '%DB_CONNECTION%'
        implements: Purple\Libs\Harmony\Interface\DatabasePlatform

    schema:
        class: Purple\Libs\Harmony\Schema
        arguments:
        - '@adapter'
        - '@sql_builder'
        - '@event_dispatcher'

    executor:
        class: Purple\Libs\Harmony\QueryExecutor
        arguments:
        - '@adapter'
        - '@event_dispatcher'

    migration_manager:
        class: Purple\Libs\Harmony\MigrationManager
        arguments:
        - '@harmony'
        - '%migrations_dir%'
        - '%migrations_table%'

    xpressive:
        class: Purple\Libs\Harmony\Xpressive
        autowire: true
        tags: ['database']

    mailer:
        class: Purple\Core\Mailer

    database:
        class: Purple\Core\DbaseConnection
        alias: database
        tags: ['example']

    logger:
        class: Purple\Core\FileLogger
        arguments:
        - '@mailer'
        asGlobal: true
        asShared: true
        addServiceMiddleware: Purple\Core\MiddlewareExamples\LoggingMiddlewares

    some_service:
        class: Purple\Core\SomeService
        method_calls:
        - [doSomething, []]
        tags: ['example']
        autowire: true

    #add services from files scanning and adding tagging   
    discovery:
    App\Directory\:
        resource: '../core/Db/Example/*'
        exclude: '../core/Db/Example/{DependencyInjection,Entity,Migrations,Tests}'
```

```php

//index.php

// Define the cache configuration
$cacheType = 'memory'; // or 'file', or 'memory' or 'redis'
$cacheConfig = [
    'redis' => [
        'scheme' => 'tcp',
        'host'   => '127.0.0.1',
        'port'   => 6379,
    ],
    'maxSize' => 1000,
    'evictionPolicy' => 'LRU'
];


// Create the cache instance using the factory
$cache = CacheFactory::createCache($cacheType, $cacheConfig);

// Initialize the DI container with the log path using monolog 
$logFilePath = __DIR__ . '/../bootstrap/logs/container.log';

// Initialize the DI container with the log path using monolog
$logFilePath = __DIR__ . '/../bootstrap/logs/container.log';
$configDir = __DIR__ . '/../config';
$resourceDir = __DIR__ . '/../resources';

$eventDispatcher = new EventDispatcher();
// Create the kernel
$kernel = new Kernel('prod', false, $logFilePath, $cache, $configDir, $resourceDir, $eventDispatcher );

// Load environment variables
$kernel->loadEnv(__DIR__ . '/../.env');

// Load service configurations from a file (e.g., YAML)
$kernel->loadConfigurationFromFile(__DIR__ . '/../config/services.yaml');

// Define services in PHP 
$kernel->loadConfigurationFromFile(__DIR__ . '/../public/service.php');

//event dispatcher boot
$eventDispatcher->addListener('kernel.pre_boot', function() {
    echo "Kernel is about to boot!\n";
});
$eventDispatcher->addListener('kernel.post_boot', function() {
    echo "Kernel has finished booting!\n";
});

//$kernel->addExtension(new DatabaseExtension());
//auto discovery of services feature outside the yaml and php file
$kernel->autoDiscoverServices('../core/Db/Example', 'Purple\Core\Db\Example');

// Add any compiler passes
$kernel->addCompilerPass(new CustomCompilerPass('exampletag','examplemethod'));

// Boot the kernel and get the container
$kernel->boot();
$container = $kernel->getContainer();


$container->annotationDiscovery([
    'namespace' => [
        'Purple\\Core\\AnnotEx' => [
            'resource' => __DIR__.'/../core/AnnotEx',
            'exclude' => []
        ]
    ]
]);

//get service by tag 
$databaseServices = $container->getByTag('example');

//get all services with a tag name
$databaseServices = $container->findTaggedServiceIds('purple.core.db.example.autowired');

// Retrieve and use services as usual
$harmony = $container->get('harmony');
$schema = $container->get('schema');


```

```php
//example compiler pass
<?php

namespace Purple\Core\Services\CompilerPass;

use Purple\Core\Services\Container;
use Purple\Core\Services\Interface\CompilerPassInterface;
use Purple\Core\Services\ContainerConfigurator;
use Purple\Core\Services\Kernel\PassConfig;
use Purple\Core\Services\Reference;

class CustomCompilerPass implements CompilerPassInterface
{
    private $tagName;
    private $methodName;

    public function __construct(string $tagName, string $methodName)
    {
        $this->tagName = $tagName;
        $this->methodName = $methodName;
    }
    /**
     * Modify the container here before it is dumped to PHP code.
     */
    public function process(ContainerConfigurator $containerConfigurator): void
    {
        // Example: Tag all services with a specific interface
        $taggedServices = $containerConfigurator->findTaggedServiceIds('example');

        //print_r( $taggedServices);

        foreach ($taggedServices as $id => $tags) {
            // Set the currentservice
            $containerConfigurator->setCurrentService($tags);
            echo $tags;
            // Add a method call to each tagged service
            $containerConfigurator->addMethodCall('setTester', [new Reference('logger')]);
            // You can also modify other aspects of the service definition here
        }
    }

    /**
     * Get the priority of this compiler pass.
     * 
     * @return int The priority (higher values mean earlier execution)
     */
    public function getPriority(): int
    {
        // This compiler pass will run earlier than default priority passes
        return 10;
    }

    /**
     * Get the type of this compiler pass.
     * 
     * @return string One of the TYPE_* constants in Symfony\Component\DependencyInjection\Compiler\PassConfig
     */
    public function getType(): string
    {
        // This pass runs before optimization, allowing it to modify service definitions
        return PassConfig::TYPE_BEFORE_OPTIMIZATION;
    }
}
```
```php
//config_prod.php
<?php

return [
    'host' => 'localhost',
    'database' => 'games',
    'username' => 'root',
    'password' => '',
    'driver' => 'mysql',
    'cache' => [
        'driver' => 'redis',
        'host' => 'redis.example.com',
        'port' => 6379,
    ],
    'debug' => false,
    'log_level' => 'error',
    // You can even use environment variables or conditionals
    'api_key' => getenv('API_KEY') ?: 'default_api_key',
    'feature_flags' => [
        'new_feature' => PHP_VERSION_ID >= 70400,
    ],
];
```