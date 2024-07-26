<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables from .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();


error_reporting(E_ALL);
ini_set('display_errors', 1);


Purple\Core\Session\SessionManager::Start();


//============================================

use Purple\Core\Services\Container;
use Purple\Libs\Cache\Factory\CacheFactory;
use Purple\Core\Services\Kernel\Kernel;
use Purple\Core\Services\CompilerPass\CustomCompilerPass;
use Purple\Core\EventsExamples\EventDispatcher;
use Purple\Core\EventsExamples\DatabaseExtension;

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

$kernel->addExtension(new DatabaseExtension());
//auto discovery of services feature outside the yaml and php file
$container->autoDiscover('../src/Directory', 'App\Directory');
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
$migrationManager = $container->get('migration_manager');
$xpressive = $container->get('xpressive'); //autowired
$some_service = $container->get('some_service');

//print_r($container->get('example_cacher'));
print_r($container->get('database_connection'));



die();


