<?php
namespace Purple\Core;

use PHPUnit\Framework\TestCase;
use Purple\Core\Container;
use Purple\Core\Exceptions\ContainerException;
use Purple\Core\Exceptions\ServiceNotFoundException;;

class ContainerTest extends TestCase
{
    private $container;

    protected function setUp(): void
    {
        $definitions = [
            'parameters' => [
                'config.database_path' => '/var/data/db',
            ],
            'services' => [
                'db_adapter' => [
                    'class' => MySQLAdapter::class,
                    'arguments' => ['%config.database_path%'],
                    'scope' => 'singleton',
                    'visibility' => 'public',
                ],
                'db_service' => [
                    'class' => DatabaseService::class,
                    'arguments' => ['@db_adapter'],
                ],
                'redis' => [
                    'class' => Redis::class,
                   
                ],
                'user_service' => [
                    'class' => UserService::class,
                    'arguments' => ['@db_service'],
                    'lazy' => true,
                ],
                'method_lazy_service' => [
                    'class' => ExpensiveService::class,
                    'lazy' => 'method',
                    'lazy_method' => 'initialize',
                ],
                'base_adapter' => [
                    'class' => MySQLAdapter::class,
                    'arguments' => ['%config.database_path%'],
                    'scope' => 'singleton',
                    'visibility' => 'private', // Set visibility to private
                ],
                'cache_service' => [
                    'class' => CacheService::class,
                    'arguments' => ['@redis'],
                    'calls' => [
                        ['initialize', []], // Call initialize method after service instantiation
                        //['warmCache', ['param1', 'param2']], // Call warmCache method with parameters
                    ],
                ],
                'report_factory' => [
                    'factory' => [ReportFactory::class, 'createReport'], // Using factory method
                ],
                'document_service' => [
                    'class' => DocumentService::class,
                    'arguments' => ['@report_factory'],
                ],
            ],
            'interfaces' => [
                DatabaseInterface::class => 'mysql_database', // Resolve implementation dynamically
            ],
        ];

        $this->container = new Container($definitions, __DIR__ . '/.env');
    }

    public function testGetService()
    {
        $dbAdapter = $this->container->get('db_adapter');
        $this->assertInstanceOf(MySQLAdapter::class, $dbAdapter);

        $dbService = $this->container->get('db_service');
        $this->assertInstanceOf(DatabaseService::class, $dbService);
        $this->assertSame($dbAdapter, $dbService->getAdapter());
    }

    public function testLazyLoading()
    {
        $userService = $this->container->get('user_service');
        $this->assertInstanceOf(LazyProxy::class, $userService);
    }

    public function testMethodLazyLoading()
    {
        $methodLazyService = $this->container->get('method_lazy_service');
        $this->assertInstanceOf(LazyProxy::class, $methodLazyService);
    }

    public function testServiceVisibility()
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->container->get('base_adapter'); // Should throw an exception because it's private
    }

    public function testMethodCalls()
    {
        $cacheService = $this->container->get('cache_service');
        $this->assertInstanceOf(CacheService::class, $cacheService);
        // Add assertions to check that the methods were called as expected
    }

    public function testFactoryService()
    {
        $report = $this->container->get('report_factory');
        $this->assertInstanceOf(Report::class, $report);
    }

    public function testDefinitionInheritance()
    {
        $definitions = [
            'services' => [
                'base_service' => [
                    'class' => BaseService::class,
                    'arguments' => ['arg1'],
                ],
                'extended_service' => [
                    'extends' => 'base_service',
                    'arguments' => ['arg2'],
                ],
            ],
        ];

        $container = new Container($definitions);
        $extendedService = $container->get('extended_service');
        $this->assertInstanceOf(BaseService::class, $extendedService);
        // Add assertions to check that the arguments were inherited correctly
    }

    public function testCircularReferenceDetection()
    {
        $definitions = [
            'services' => [
                'service_a' => [
                    'class' => ServiceA::class,
                    'arguments' => ['@service_b'],
                ],
                'service_b' => [
                    'class' => ServiceB::class,
                    'arguments' => ['@service_a'],
                ],
            ],
        ];

        $this->expectException(ContainerException::class);
        $container = new Container($definitions);
        $container->get('service_a'); // Should throw an exception due to circular reference
    }

    public function testEnvironmentVariableIntegration()
    {
        $this->assertEquals('value', $this->container->getEnv('ENV_VAR_NAME'));
    }

    public function testServiceAliases()
    {
        $definitions = [
            'services' => [
                'primary_service' => [
                    'class' => PrimaryService::class,
                    'alias' => 'primary',
                ],
            ],
        ];

        $container = new Container($definitions);
        $service = $container->get('primary');
        $this->assertInstanceOf(PrimaryService::class, $service);
    }

    public function testParameterInjection()
    {
        $dbService = $this->container->get('db_service');
        $this->assertEquals('/var/data/db', $dbService->getDatabasePath());
    }

    public function testInvalidServiceDefinition()
    {
        $definitions = [
            'services' => [
                'invalid_service' => [
                    // Invalid service definition without 'class' or 'factory'
                ],
            ],
        ];

        $this->expectException(ContainerException::class);
        $container = new Container($definitions);
        $container->get('invalid_service'); // Should throw an exception
    }

    public function testNonExistentService()
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->container->get('non_existent_service'); // Should throw an exception
    }

    public function testInheritedDefinitions()
    {
        $definitions = [
            'services' => [
                'base_service' => [
                    'class' => BaseService::class,
                    'arguments' => ['base_arg'],
                ],
                'child_service' => [
                    'extends' => 'base_service',
                    'class' => ChildService::class,
                    'arguments' => ['child_arg'],
                ],
            ],
        ];

        $container = new Container($definitions);
        $childService = $container->get('child_service');
        $this->assertInstanceOf(ChildService::class, $childService);
    }
}

// Mock classes to use in the tests

class MySQLAdapter
{
    private $databasePath;

    public function __construct($databasePath)
    {
        $this->databasePath = $databasePath;
    }

    public function getDatabasePath()
    {
        return $this->databasePath;
    }
}

class DatabaseService
{
    private $adapter;

    public function __construct(MySQLAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function getAdapter()
    {
        return $this->adapter;
    }
}

class UserService
{
    private $dbService;

    public function __construct(DatabaseService $dbService)
    {
        $this->dbService = $dbService;
    }
}

class ExpensiveService
{
    public function initialize()
    {
        // Initialization logic
    }
}

class Redis
{
    public function initialize()
    {
        // Initialization logic
    }
}

class CacheService
{
    public function initialize()
    {
        // Initialization logic
    }

    public function warmCache($param1, $param2)
    {
        // Warm cache logic
    }
}

class ReportFactory
{
    public static function createReport()
    {
        return new Report();
    }
}

class Report
{
}

class DocumentService
{
    private $reportFactory;

    public function __construct(ReportFactory $reportFactory)
    {
        $this->reportFactory = $reportFactory;
    }
}

class BaseService
{
    private $arg;

    public function __construct($arg)
    {
        $this->arg = $arg;
    }
}

class ChildService extends BaseService
{
}

interface DatabaseInterface
{
}

class ServiceA
{
    private $serviceB;

    public function __construct(ServiceB $serviceB)
    {
        $this->serviceB = $serviceB;
    }
}

class ServiceB
{
    private $serviceA;

    public function __construct(ServiceA $serviceA)
    {
        $this->serviceA = $serviceA;
    }
}

class PrimaryService
{
}

class LazyProxy
{
    // Placeholder for the LazyProxy implementation
}

// vendor/bin/phpunit  --debug --testdox --stop-on-error tests/
//vendor/bin/phpunit tests
?>

