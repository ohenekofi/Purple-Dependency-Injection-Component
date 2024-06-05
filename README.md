
# PHP Purple Container

Purple Container is a lightweight dependency injection container for managing class dependencies and performing dependency injection in PHP applications. This approach promotes separation of concerns and improves testability by decoupling your application's logic from the implementation details.

## Features Highlights
View the example folder for more features list
- Dependency resolution: Automatically resolve and inject dependencies for your classes.
- Lazy loading: Support for lazy loading of services for improved performance.
- Environment variable integration: Directly inject environment variables into services.
- Definition inheritance: Allow service definitions to extend other definitions.
- Method calls: Configure method calls to be executed on service initialization.
- Service aliases: Allow services to be referenced by multiple names.
- Service visibility: Define services as public or private.
- Circular reference detection: Detect and handle circular dependencies.

## Installation

You can install PHP Container via Composer. Run the following command in your terminal:

```
composer require purpleharmonie/dependency-injection
```

## Usage

Here's how you can use the PHP Container in your project:

1. **Create a Container Instance**: Instantiate the Container class and pass in your service definitions.

```php
use Purple\Core\Container\Container;

$definitions = [
    'parameters' => [
        // Define parameters here
    ],
    'services' => [
        // Define services here
    ],
    'interfaces' => [
        // Define interfaces here
    ]
];

$container = new Container($definitions);
```

2. **Retrieve Services**: Use the `get` method to retrieve services from the container.

```php
$service = $container->get('service_id');
//or
$service = $container->get('service_alias');
```

3. **Define Services**: Define your services in the service definitions array.

```php
$definitions = [
    'services' => [
        'service_id' => [
            'class' => YourServiceClass::class,
            'arguments' => [
                // Define arguments here
            ],
            'scope' => 'singleton',
            'alias' => 'service_alias',
            'calls' => [
                // Define method calls here
            ],
            'visibility' => 'public',
            'decorators' => [
                // Define decorators calls here
            ],
        ]
    ]
];
```

4. **Environment Variable Integration**: Inject environment variables into services.

```php
$definitions = [
    'services' => [
        'service_id' => [
            'class' => YourServiceClass::class,
            'arguments' => [
                '$ENV_VARIABLE_NAME$'
            ]
        ]
    ]
];
```
## Example
more usage example in the example folder

## Contributing

Contributions are welcome! Please feel free to submit issues and pull requests.

## License

This project is licensed under the MIT License -  file for details.

