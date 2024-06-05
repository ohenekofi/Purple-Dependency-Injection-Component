# PHP Purple Container

## Features
1. **Dependency resolution**: Automatically resolve and inject dependencies for your classes.
   - *Implementation Use*: Dependencies are typically defined in the service configuration and automatically injected when the service is retrieved from the container.

2. **Lazy Loading**: Support for lazy loading of services for improved performance.
   - *Implementation Use*: Lazy loading can be implemented for services that are not immediately needed upon container initialization.
   - Support for lazy loading of services.
   - Lazy loading strategies:
     1. Class initialization: Delay service instantiation until it is accessed.
     2. Method proxy: Create a proxy object that initializes the service when a specific method is called.
     3. Property proxy: Use proxy objects to delay access to specific properties of the service (implementation not provided).

      ```php
      'user_service' => [
         'class' => UserService::class,
         'arguments' => ['@db', '@logger'],
         'lazy' => true, // Lazy initialization using class initialization strategy
      ],

      'method_lazy_service' => [
         'class' => ExpensiveService::class,
         'lazy' => 'method', // Lazy initialization using method proxy strategy
         'lazy_method' => 'initialize', // Method to trigger lazy initialization
      ],

      'property_lazy_service' => [
         'class' => ExpensiveService::class,
         'lazy' => 'property', // Lazy initialization using property proxy strategy
         'lazy_properties' => ['expensiveProperty'], // Properties to be lazily loaded
      ],
      ```

3. **Environment variable integration**: Directly inject environment variables into services.
   - *Implementation Use*: Environment variables are typically accessed within services using a method provided by the container.
   ```php
   'db_adapter' => [
       'class' => MySQLAdapter::class,
       'arguments' => ['$DB_HOST$', '$DB_USER$', '$DB_PASS$', '$DB_NAME$'], // Injecting environment variable
   ],
   ```

4. **Definition inheritance**: Allow service definitions to extend other definitions.
   - *Implementation Use*: Child service definitions can specify a parent service from which they inherit configuration.
   ```php
   'base_adapter' => [
            'class' => MySQLAdapter::class,
            'arguments' => ['$DB_HOST$', '$DB_USER$', '$DB_PASS$', '$DB_NAME$'],
            'scope' => 'singleton',
        ],
   'db_adapter' => [
      'extends' => 'base_adapter',
      'arguments' => ['$DB_HOST$', '$DB_USER$', '$DB_PASS$', '$DB_NAME$'],
   ],
   ```

5. **Method calls**: Configure method calls to be executed on service initialization.
   - *Implementation Use*: Method calls are specified in the service definition and executed after the service is instantiated.
   ```php
   'service_with_calls' => [
       'class' => SomeService::class,
       'calls' => [
           ['initialize', []], // Call the 'initialize' method with no arguments
       ],
   ],

   'db_adapter' => [
      'extends' => 'base_adapter',
      'calls' => [
            ['setConnection', ['host' => 'localhost', 'dbname' => 'test']],  // Call the 'setConnection' method with arguments
      ],
   ],
   ```

6. **Service aliases**: Allow services to be referenced by multiple names.
   - *Implementation Use*: Aliases are defined in the service configuration to provide alternative names for services.
   ```php
   'alias_service' => [
       'class' => SomeService::class,
       // Service configuration
   ],
   'alias' => 'super', // Alias referencing another service
   ```

7. **Service visibility**: Define services as public or private.
   - *Implementation Use*: Services marked as private are inaccessible from outside the container, while public services can be accessed directly.
   ```php
   'base_adapter' => [
       'class' => MySQLAdapter::class,
       'arguments' => ['%config.database_path%'],
       'scope' => 'singleton',
       'visibility' => 'public', // Setting visibility public or private
   ],
   ```

8. **Circular reference detection**: Detect and handle circular dependencies.
   - *Implementation Use*: Circular reference detection is implemented during service resolution, where the container checks for circular dependencies and throws an exception if detected.


9. **Service Scopes**: Control the lifecycle and sharing of service instances.
   - *Implementation Use*: Services can be defined with different scopes, such as singleton (shared instance) or prototype (new instance per request).
   ```php
   'mysql_adapter' => [
       'extends' => 'base_adapter',
       'scope' => 'singleton', // Singleton scope (shared instance)
   ],

   'query_builder' => [
       'class' => QueryBuilder::class,
       'factory' => ['@concrete_query_builder_factory', 'create'],
       'scope' => 'prototype', // Prototype scope (new instance per request)
       'alias' => 'qb',
       'visibility' => 'public',
   ],
   ```

10. **Service Decorators**: Wrap services with decorators to extend or modify their behavior.
    - *Implementation Use*: Decorators are specified in the service definition and applied to the service during instantiation.
    ```php
    'harmony' => [
        'class' => Harmony::class,
        'arguments' => ['@mysql_adapter', '@mysql_query_builder', '@schema', '@query_executor', '@container', '%harmonyConfig%'],
        'decorators' => [
            LoggingDecorator::class, // Apply the LoggingDecorator to the service
        ],
        'scope' => 'singleton',
        'alias' => 'harmony',
        'calls' => [
            ['initialize', []],
        ],
        'visibility' => 'public',
    ],
    ```

11. **Parameter Injection**: Inject parameters (configuration values) into services.
    - *Implementation Use*: Parameters are defined in the `parameters` section and can be injected into service definitions using a specific syntax.
    ```php
    'parameters' => [
        'db.host' => 'localhost',
        'db.port' => 3306,
        // Other parameters...
    ],

    'services' => [
        'db' => [
            'class' => DatabaseService::class,
            'arguments' => [
                '%db.host%', // Injecting a parameter
                '%db.port%', // Injecting another parameter
            ],
        ],
    ],
    ```

12. **Service Factories**: Define services using factories instead of classes.
    - *Implementation Use*: Services can be instantiated using factory methods or callable functions specified in the service definition.
    ```php
    'connection_and_adapter' => [
        'factory' => ['@connection_factory', 'create'], // Using a factory method
    ],

    'container' => [
        'factory' => function () use (&$container) {
            return $container; // Using a callable function as a factory
        },
        'scope' => 'singleton',
    ],
    ```

13. **Interface Mapping**: Map interfaces to concrete service implementations.
    - *Implementation Use*: The `interfaces` section of the configuration maps interfaces to their corresponding service implementations.
    ```php
    'interfaces' => [
        DatabaseAdapterInterface::class => 'mysql_adapter',
        DatabaseBuilderInterface::class => 'query_builder',
    ],
    ```

Absolutely, I can include those features in the list. Here's how it would look:

14. **Passing Complex Parameters to Services**: Support for injecting complex parameters, such as nested arrays or objects, into services.
    - *Implementation Use*: Complex parameters are defined in the `parameters` section and can be injected into service definitions using a specific syntax.
    ```php
    'parameters' => [
        'harmonyConfig' => [
            'setting1' => 'value1',
            'setting2' => 'value2',
            // Other configuration parameters...
        ],
    ],

    'services' => [
        'harmony' => [
            'class' => Harmony::class,
            'arguments' => ['@mysql_adapter', '@mysql_query_builder', '@schema', '@query_executor', '@container', '%harmonyConfig%'],
            // Other service configuration...
        ],
    ],
    ```

15. **Injecting the Container Instance**: Ability to inject the container instance itself into services.
    - *Implementation Use*: The container instance can be injected into a service by referencing the `@container` service in the service definition's arguments.
    ```php
    'services' => [
        'harmony' => [
            'class' => Harmony::class,
            'arguments' => ['@mysql_adapter', '@mysql_query_builder', '@schema', '@query_executor', '@container', '%harmonyConfig%'], // Injecting the container instance
            // Other service configuration...
        ],

        'container' => [
            'factory' => function () use (&$container) {
                return $container;
            },
            'scope' => 'singleton',
        ],
    ],
    ```

After carefully reviewing the `Container` class code, I don't believe we have left out any significant features from the list. However, here are a few additional minor points that could be mentioned:

16. **Constructor Argument Autowiring**: The ability to automatically resolve and inject constructor arguments of a service based on their type hints.
    - *Implementation Use*: If no explicit `arguments` are defined for a service, the container will attempt to autowire the constructor arguments by resolving dependencies from other services or parameters based on their type hints.
    ```php
    'services' => [
        'some_service' => [
            'class' => SomeService::class,
            // No explicit 'arguments' defined, constructor arguments will be autowired
        ],
    ],
    ```

17. **Handling Abstract Classes and Interfaces**: The container can handle instantiation of abstract classes and interfaces by using a factory definition.
    - *Implementation Use*: If a service definition references an abstract class or an interface, the container expects a `factory` to be defined for that service, which provides the concrete implementation.
    ```php
    'services' => [
        'abstract_service' => [
            'class' => 'App\Services\AbstractService',
            'factory' => ['@logger', 'createAbstractService'],
        ],
    ],
    ```

18. **Resolving Environment Variables**: The container can resolve environment variables in service definitions and parameter values.
    - *Implementation Use*: Environment variables are prefixed with `$` and can be used in service definitions or parameter values.
    ```php
    'parameters' => [
        'db.host' => '$env.database_host',
    ],

    'services' => [
        'db' => [
            'class' => DatabaseService::class,
            'arguments' => ['$env.database_host'],
        ],
    ],
    ```


