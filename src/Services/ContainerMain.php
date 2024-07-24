<?php
namespace Purple\Core\Services;

use Exception;
use Symfony\Component\Yaml\Yaml;
use ReflectionClass;
use ReflectionMethod;
use Purple\Core\Services\ContainerConfigurator;
use Purple\Core\Services\ServiceDiscovery;
use Purple\Core\Services\Exception\ServiceNotFoundException;
use Purple\Core\Services\Exception\DependencyResolutionException;
use Purple\Libs\Cache\Interface\CacheInterface;
use Purple\Libs\Cache\Cache\RedisCache;
use Purple\Libs\Cache\Cache\FileCache;
use Purple\Libs\Cache\Cache\InMemoryCache;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Purple\Core\Services\DependencyResolver;
use Purple\Core\Services\ServiceFactory;
use Purple\Core\Services\ConfigurationLoader;

class Container
{
    const SCOPE_SINGLETON = 'singleton';
    const SCOPE_REQUEST = 'request';
    const SCOPE_PROTOTYPE = 'prototype';
    const SCOPE_PROXY = 'proxy';
    const SCOPE_PRIVATE = "private";

    private array $parameters = [];
    private array $services = [];
    private array $aliases = [];
    private array $tags = [];
    private array $middlewares = [];
    private array $dependencyGraph = [];
    private array $resolvedServices = [];
    private array $requestScopedServices = [];
    private array $dependencyStack = [];
    private array $resolving = [];
    private array $circularDependencies = [];
    private CacheInterface $cache;
    private Logger $logger;


    public function __construct(string $logFilePath,  CacheInterface $cache)
    {
      $this->logger = new Logger('container');
      $this->logger->pushHandler(new StreamHandler($logFilePath, Logger::DEBUG));
      $this->cache = $cache;
    
    }

    public function enableServiceCaching()
    {
        $cachedGraph = $this->cache->get('dependency_graph');

        //print_r( $cachedGraph);

        if ($cachedGraph) {
            $this->definitions = $cachedGraph;
            $this->logger->info('Loaded services from cache.');
        } else {
            $this->logger->info('Building dependency graph and caching services.');
            $this->cache->set('dependency_graph', $this->definitions);
        }
    }

    public function getDefinitionforCache(){
        return $this->definitions;
    }

    public function loadEnv($file)
    {
        if (!file_exists($file)) {
            throw new Exception("Env file $file not found");
        }
        

        $env = parse_ini_file($file);
        foreach ($env as $key => $value) {
            $this->setParameter($key, $value);
        }
        //print_r($this->parameters);
    }


    public function loadConfigurationFromFile($filePath)
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        switch ($extension) {
            case 'yaml':
            case 'yml':
                $config = Yaml::parseFile($filePath);
                $this->processYamlConfig($config);
                break;

            case 'php':
                $phpServices = include $filePath;
                if (is_callable($phpServices)) {
                    $configurator = new ContainerConfigurator($this);
                    $phpServices($configurator);
                } else {
                    throw new Exception("PHP configuration file must return a callable");
                }
                break;

            default:
                throw new Exception("Unsupported configuration file format: " . $extension);
        }

          echo "<pre>";
        //print_r($this->services);
        $this->generateDependencyGraph();
        $this->detectCircularDependencies();

        if (!empty($this->circularDependencies)) {
            $this->handleCircularDependencies();
        }

        //echo "<pre>";
        //print_r($this->dependencyGraph);
    }

    private function processYamlConfig(array $config)
    {
        if (isset($config['parameters'])) {
            foreach ($config['parameters'] as $key => $value) {
                $this->setParameter($key, $value);
            }
        }

        if (isset($config['services'])) {
            foreach ($config['services'] as $id => $serviceConfig) {
                if (is_string($serviceConfig)) {
                    // If the service config is just a string, assume it's the class name
                    $this->set($id, $serviceConfig);
                } elseif (is_array($serviceConfig)) {
                    // If it's an array, process the configuration
                    $this->set($id, $serviceConfig['class'] ?? null);
                    $this->configureService($id, $serviceConfig);
                } else {
                    $this->logger->warning("Invalid service configuration for '$id'");
                }
            }
        }
        // New discovery mode
        if (isset($config['discovery'])) {
            $discovery = new ServiceDiscovery($this);
            $services = $discovery->discoverFromConfig($config['discovery']);
            foreach ($services as $id => $definition) {
                $this->set($id, $definition['class']);
                if (!empty($definition['arguments'])) {
                    $this->arguments($id, $definition['arguments']);
                }
                if ($definition['autowire']) {
                    $this->autowire($id);
                }
                if (!empty($definition['tags'])) {
                    foreach ($definition['tags'] as $tag) {
                        $this->addTag($id, $tag);
                    }
                }
            }
        }
        

    }

    private function configureService($id, array $config)
    {
        if (isset($config['arguments'])) {
            $this->arguments($id, $config['arguments']);
        }
        if (isset($config['method_calls'])) {
            foreach ($config['method_calls'] as $call) {
                $this->addMethodCall($id, $call[0], $call[1] ?? []);
            }
        }
        if (isset($config['tags'])) {
            foreach ($config['tags'] as $tag) {
                $this->addTag($id, $tag);
            }
        }
        if (isset($config['autowire'])) {
            $this->autowire($id);
        }
        if (isset($config['alias'])) {
            $this->setAlias($config['alias'], $id);
        }
        if (isset($config['factory'])) {
            $this->factory($id, $config['factory']);
        }
        if (isset($config['implements'])) {
            $this->implements($id, $config['implements']);
        }
        if (isset($config['scope'])) {
            $this->scope($id, $config['scope']);
        }
    }

    public function set($id, $class)
    {
        $this->services[$id] = ['class' => $class];
        //$this->updateDependencyGraph($id);

        return $this;
    }

    //not fully implemented
    private function updateDependencyGraph($id)
    {
        if (!isset($this->services[$id])) {
            throw new ServiceNotFoundException("Service '$id' not found");
        }
        
        $serviceConfig = $this->services[$id];
        $this->dependencyGraph[$id] = $this->resolveDependencies($id, $serviceConfig);
        
        // Optionally, update cache if you're using caching
        if (isset($this->cache)) {
            $this->cache->set('dependency_graph', $this->dependencyGraph);
        }
        
        $this->logger->info("Dependency graph updated for service: $id");
    }



    public function alias($alias, $service)
    {
        $this->aliases[$alias] = $service;
        return $this;
    }

    public function setAlias($alias, $service)
    {
        return $this->alias($alias, $service);
    }

    public function addTag($id, $tag, $attributes = [])
    {
        if (!isset($this->tags[$tag])) {
            $this->tags[$tag] = [];
        }
        $this->tags[$tag][$id] = $attributes;
        return $this;
    }

    public function findTaggedServiceIds($tag)
    {
        return $this->tags[$tag] ?? [];
    }

    public function addMiddleware($middleware)
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function scope($id, $scope)
    {
        if (!in_array($scope, [self::SCOPE_SINGLETON, self::SCOPE_REQUEST, self::SCOPE_PROTOTYPE, self::SCOPE_PROXY, self::SCOPE_PRIVATE])) {
            throw new Exception("Invalid scope: $scope");
        }
        $this->services[$id]['scope'] = $scope;
        return $this;
    }

    public function public($id)
    {
        $this->services[$id]['public'] = true;
        return $this;
    }

    public function private($id)
    {
        $this->services[$id]['public'] = false;
        return $this;
    }

    public function decorate($id, $decoratorClass, $arguments = [])
    {
        $this->services[$id]['decorator'] = [
            'class' => $decoratorClass,
            'arguments' => $arguments
        ];
        return $this;
    }

    public function shared($id)
    {
        return $this->scope($id, self::SCOPE_SINGLETON);
    }

    public function setLazy($id, $lazy = true)
    {
        $this->services[$id]['lazy'] = $lazy;
        return $this;
    }

    public function addArgument($id, $argument)
    {
        $this->services[$id]['arguments'][] = $argument;
        return $this;
    }

    public function arguments($id, array $arguments)
    {
        $this->services[$id]['arguments'] = $arguments;
        return $this;
    }

    public function setParameter($name, $value)
    {
        $this->parameters[$name] = $value;
        return $this;
    }

    public function addMethodCall($id, $method, $arguments = [])
    {
        $this->services[$id]['method_calls'][] = [$method, $arguments];
        return $this;
    }

    public function factory($id, $factory)
    {
        $this->services[$id]['factory'] = $factory;
        return $this;
    }

    public function implements($name, $interface)
    {
        if (!isset($this->services[$name])) {
            throw new Exception("Service $name not found");
        }
        $this->services[$name]['implements'] = $interface;
        $this->aliases[$interface] = $name;
    }

    public function extends($id, $abstract)
    {
        $this->services[$id]['extends'] = $abstract;
        return $this;
    }

    public function autowire($id)
    {
        $this->services[$id]['autowire'] = true;
        return $this;
    }

    public function getParameter($name)
    {
        if (!isset($this->parameters[$name])) {
            throw new Exception("Parameter '$name' not found");
        }
        return $this->parameters[$name];
    }

    public function getService($id)
    {
        if (isset($this->aliases[$id])) {
            $id = $this->aliases[$id];
        }

        if (!isset($this->services[$id])) {
            throw new ServiceNotFoundException("Service '$id' not found");
        }

        if (!isset($this->services[$id]['instance'])) {
            $this->services[$id]['instance'] = $this->createService($id);
        }

        return $this->services[$id]['instance'];
    }

    public function get($id)
    {
        if (!isset($this->dependencyGraph[$id])) {
            throw new ServiceNotFoundException("Service '$id' not found");
        }

        if (isset($this->resolving[$id])) {
            throw new DependencyResolutionException("Circular dependency detected for service '$id'");
        }

        $definition = $this->dependencyGraph[$id];
        $scope = $definition['scope'] ?? self::SCOPE_SINGLETON;

        $this->resolving[$id] = true;
        $this->dependencyStack[] = $id;

        try {
            $service = $this->resolveService($id, $scope);
        } finally {
            unset($this->resolving[$id]);
            array_pop($this->dependencyStack);
        }

        return $service;
    }

    private function resolveService($id, $scope)
    {
        switch ($scope) {
            case self::SCOPE_SINGLETON:
                return $this->getSingletonService($id);
            case self::SCOPE_REQUEST:
                return $this->getRequestScopedService($id);
            case self::SCOPE_PROTOTYPE:
                return $this->createService($id);
            case self::SCOPE_PROXY:
                return $this->getProxyService($id);
            case self::SCOPE_PRIVATE:
                throw new Exception("Cannot access private service '$id'");
            default:
                throw new Exception("Unknown scope for service '$id'");
        }
    }

    private function getSingletonService($id)
    {
        if (!isset($this->resolvedServices[$id])) {
            $this->resolvedServices[$id] = $this->createService($id);
        }
        return $this->resolvedServices[$id];
    }

    private function getRequestScopedService($id)
    {
        if (!isset($this->requestScopedServices[$id])) {
            $this->requestScopedServices[$id] = $this->createService($id);
        }
        return $this->requestScopedServices[$id];
    }

    private function getProxyService($id)
    {
        // Implementation of proxy service goes here
        // This is a placeholder and should be implemented based on your specific needs
        throw new Exception("Proxy scope not implemented yet");
    }

    public function resetRequestScopedServices()
    {
        $this->requestScopedServices = [];
    }

    private function resolveReferences($arguments)
    {
        $resolved = [];
        foreach ($arguments as $arg) {
            if (isset($arg['type']) && $arg['type'] === 'service_reference') {
                $resolved[] = $this->get($arg['id']);
            } elseif (isset($arg['type']) && $arg['type'] === 'parameter') {
                //$resolved[] = $arg['value'];
                //echo   $this->getParameter($arg['name']);
                $resolved[] = $this->getParameter($arg['name']);
            } elseif (isset($arg['type']) && $arg['type'] === 'default') {
                if(empty($arg['value'])){
                    $resolved[] = $arg['value'];
                }else{
                    $resolved[] = $this->getParameter($arg['value']);
                }
                
            }else {
                $resolved[] = $arg;
            }
        }
        return $resolved;
    }

    private function createService($id)
    {
        if (!isset($this->dependencyGraph[$id])) {
            throw new ServiceNotFoundException("Service '$id' not found");
        }

        $definition = $this->dependencyGraph[$id];

        // Handle factory creation
        if (isset($definition['factory_info'])) {
            $factory = $definition['factory_info']['factory'];
            $arguments = $this->resolveReferences($definition['factory_info']['arguments']);
            
            if ($factory['type'] === 'class') {
                $factoryInstance = new $factory['class']();
            } else {
                $factoryInstance = $this->get($factory['name']);
            }

            $instance = call_user_func_array([$factoryInstance, $factory['method']], $arguments);
        } else {
            // Create the service instance
            $arguments = $this->resolveReferences($definition['arguments']);
            $reflectionClass = new ReflectionClass($definition['class']);
            $instance = $reflectionClass->newInstanceArgs($arguments);
        }

        // Handle method calls
        if (!empty($definition['method_calls'])) {
            foreach ($definition['method_calls'] as $methodCall) {
                $method = $methodCall[0];
                $methodArguments = $this->resolveReferences($methodCall[1]);

                //print_r($methodArguments);
                call_user_func_array([$instance, $method], $methodArguments);
            }
        }

        if ($definition['scope'] === self::SCOPE_PRIVATE) {
            $this->resolvedServices[$id] = $instance;
        }

        return $instance;
    }

    private function createServiceFromFactory($definition)
    {
        $factory = $definition['factory'];
        $factoryClass = $factory[0];
        $factoryMethod = $factory[1];

        $factoryInstance = is_string($factoryClass) ? new $factoryClass() : $factoryClass;
        $arguments = $this->resolveArguments($definition['arguments'] ?? []);

        return call_user_func_array([$factoryInstance, $factoryMethod], $arguments);
    }



    private function findServiceByType(string $type)
    {
        foreach ($this->services as $serviceName => $serviceConfig) {
            if (isset($serviceConfig['implements']) && $serviceConfig['implements'] === $type) {
                return $serviceName;
            }
            if (isset($serviceConfig['class']) && $serviceConfig['class'] === $type) {
                return $serviceName;
            }
        }
        return null;
    }

    public function generateDependencyGraph()
    {
        foreach ($this->services as $serviceName => $serviceConfig) {
            $this->dependencyGraph[$serviceName] = $this->resolveDependencies($serviceName, $serviceConfig);
        }
    }

    private function detectCircularDependencies()
    {
        $this->circularDependencies = [];
        foreach ($this->dependencyGraph as $id => $definition) {
            $this->dependencyStack = [];
            $this->resolving = [];
            try {
                $this->resolveServiceDependencies($id);
            } catch (CircularDependencyException $e) {
                $this->circularDependencies[$id] = $this->dependencyStack;
            }
        }
    }

    
    private function resolveServiceDependencies($id)
    {
        if (!isset($this->dependencyGraph[$id])) {
            throw new ServiceNotFoundException("Service '$id' not found");
        }

        if (isset($this->resolving[$id])) {
            throw new CircularDependencyException("Circular dependency detected for service '$id'");
        }

        $this->resolving[$id] = true;
        $this->dependencyStack[] = $id;

        $definition = $this->dependencyGraph[$id];

        try {
            if (isset($definition['arguments'])) {
                foreach ($definition['arguments'] as $arg) {
                    if (isset($arg['type']) && $arg['type'] === 'service_reference') {
                        $this->resolveServiceDependencies($arg['id']);
                    }
                }
            }

            if (isset($definition['method_calls'])) {
                foreach ($definition['method_calls'] as $methodCall) {
                    foreach ($methodCall[1] as $arg) {
                        if (isset($arg['type']) && $arg['type'] === 'service_reference') {
                            $this->resolveServiceDependencies($arg['id']);
                        }
                    }
                }
            }
        } finally {
            unset($this->resolving[$id]);
            array_pop($this->dependencyStack);
        }
    }

    private function handleCircularDependencies()
    {
        $message = "Circular dependencies detected:\n";
        foreach ($this->circularDependencies as $service => $dependencyChain) {
            $message .= "Service '$service': " . implode(' -> ', $dependencyChain) . " -> $service\n";
        }
        $this->logger->error($message);
        
        // You can choose to throw an exception here if you want to prevent the application from starting
        // throw new CircularDependencyException($message);
        
        // Or you can just log the error and continue, allowing the application to run but with potential issues
        error_log($message);
    }

    public function loadAllServices()
    {
        foreach ($this->dependencyGraph as $id => $definition) {
            $scope = $definition['scope'] ?? self::SCOPE_SINGLETON;
            if ($scope === self::SCOPE_SINGLETON) {
                $this->getSingletonService($id);
            }
        }
    }

    private function resolveDependencies($serviceName, $serviceConfig)
    {
        $resolvedConfig = [
            'class' => $serviceConfig['class'] ?? null,
            'arguments' => [],
            'method_calls' => [],
            'tags' => $serviceConfig['tags'] ?? [],
            'factory' => $serviceConfig['factory'] ?? null,
            'implements' => $serviceConfig['implements'] ?? null,
            'scope' => $serviceConfig['scope'] ?? 'singleton',
            'lazy' => $serviceConfig['lazy'] ?? false,
            'autowire' => $serviceConfig['autowire'] ?? false,
        ];

        // Resolve arguments
        if (isset($serviceConfig['arguments'])) {
            $resolvedConfig['arguments'] = $this->resolveArgumentsRecursively($serviceConfig['arguments']);
        } elseif ($resolvedConfig['autowire'] && $resolvedConfig['class']) {
            $resolvedConfig['arguments'] = $this->autowireArgumentsRecursively($resolvedConfig['class']);
        }

        // Resolve method calls
        if (isset($serviceConfig['method_calls'])) {
            foreach ($serviceConfig['method_calls'] as $methodCall) {
                $methodName = $methodCall[0];
                $methodArguments = $methodCall[1] ?? [];

                if ($resolvedConfig['autowire'] && empty($methodArguments)) {
                    $methodArguments = $this->autowireMethodArgumentsRecursively($resolvedConfig['class'], $methodName);
                } else {
                    $methodArguments = $this->resolveArgumentsRecursively($methodArguments);
                }

                $resolvedConfig['method_calls'][] = [$methodName, $methodArguments];
            }
        }

        // Handle factory and get concrete class
        if ($resolvedConfig['factory']) {
            $factoryInfo = $this->handleFactoryRecursively([
                'factory' => $resolvedConfig['factory'],
                'arguments' => $resolvedConfig['arguments']
            ]);
            $resolvedConfig['factory_info'] = $factoryInfo;
            
            // Use concrete class if available
            if (isset($factoryInfo['concrete_class'])) {
                $resolvedConfig['class'] = $factoryInfo['concrete_class'];
            }
        }

        return $resolvedConfig;
    }



    private function resolveArguments(array $arguments)
    {
        $resolvedArguments = [];

        foreach ($arguments as $key => $argument) {
            if ($argument instanceof Reference) {
                $resolvedArguments[$key] = $this->getService($argument->getId());
            } elseif (is_string($argument) && strpos($argument, '@') === 0) {
                // Handle string references (e.g., '@service_name')
                $serviceId = substr($argument, 1);
                $resolvedArguments[$key] = $this->getService($serviceId);
            } elseif (is_array($argument)) {
                $resolvedArguments[$key] = $this->resolveArguments($argument);
            }  elseif (is_string($argument) && preg_match('/^\$(.+)\$$/', $argument, $matches)) {
                //print_r($matches);
                $resolvedArguments[$key] = $this->getParameter($matches[1]);
            }else {
                $resolvedArguments[$key] = $this->resolveParameter($argument);
            }
        }

        return $resolvedArguments;
    }

    private function resolveParameter($value)
    {
        if (is_string($value) && strpos($value, '%') === 0 && substr($value, -1) === '%') {
            $paramName = substr($value, 1, -1);
            return $this->parameters[$paramName] ?? $value;
        }
        return $value;
    }

    private function resolveArgumentsRecursively(array $arguments)
    {
        $resolvedArguments = [];

        foreach ($arguments as $key => $argument) {
            if ($argument instanceof Reference) {
                $serviceId = $argument->getId();
                $resolvedArguments[$key] = [
                    'type' => 'service_reference',
                    'id' => $serviceId
                ];
            } elseif (is_string($argument) && strpos($argument, '@') === 0) {
                $serviceId = substr($argument, 1);
                $resolvedArguments[$key] = [
                    'type' => 'service_reference',
                    'id' => $serviceId
                ];
            } elseif (is_array($argument)) {
                $resolvedArguments[$key] = $this->resolveArgumentsRecursively($argument);
            } elseif (is_string($argument) && preg_match('/^\$(.+)\$$/', $argument, $matches)) {
                $resolvedArguments[$key] = [
                    'type' => 'parameter',
                    'name' => $matches[1],
                    'value' => $this->getParameter($matches[1])
                ];
            } else {
                $resolvedArguments[$key] = $this->resolveParameter($argument);
            }
        }

        return $resolvedArguments;
    }


    private function autowireArgumentsRecursively(string $className)
    {
        if (!$this->isInstantiableClass($className)) {
            throw new Exception("Cannot autowire arguments for non-instantiable class: $className");
        }

        $reflectionClass = new ReflectionClass($className);
        $constructor = $reflectionClass->getConstructor();

        if (!$constructor) {
            return [];
        }

        $parameters = $constructor->getParameters();
        return $this->resolveAutowiredParametersRecursively($parameters);
    }

    private function autowireMethodArgumentsRecursively(string $className, string $methodName)
    {
        if (!$this->isInstantiableClass($className)) {
            throw new Exception("Cannot autowire method arguments for non-instantiable class: $className");
        }

        $reflectionClass = new ReflectionClass($className);
        $method = $reflectionClass->getMethod($methodName);

        
        if ($method->isAbstract()) {
            throw new Exception("Cannot autowire arguments for abstract method: $className::$methodName");
        }

        $parameters = $method->getParameters();
        return $this->resolveAutowiredParametersRecursively($parameters);
    }

    private function isInstantiableClass(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }
        
        $reflectionClass = new ReflectionClass($className);
        return !$reflectionClass->isAbstract() && !$reflectionClass->isInterface() && !$reflectionClass->isTrait();
    }

    private function resolveAutowiredParametersRecursively(array $parameters)
    {
        $resolvedParameters = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type && !$type->isBuiltin()) {
                $typeName = $type->getName();

                //there is no real need for this in this section as some of argument for a class could be interfaces 
                //that will resolved through factory classes
                if (!$this->isInstantiableClass($typeName)) {
                    //throw new Exception("Cannot autowire non-instantiable type: $typeName");
                }

                $service = $this->findServiceByType($typeName);

                if ($service) {
                    $resolvedParameters[] = [
                        'type' => 'service_reference',
                        'id' => $service
                    ];
                } else {
                    $resolvedParameters[] = [
                        'type' => 'service_reference',
                        'id' => $typeName
                    ];
                }
            } elseif ($type && $type->isBuiltin()) {
                $paramName = $parameter->getName();
                if (isset($this->parameters[$paramName])) {
                    $resolvedParameters[] = [
                        'type' => 'parameter',
                        'name' => $paramName,
                        'value' => $this->resolveParameter('%' . $paramName . '%')
                    ];
                } elseif ($parameter->isOptional()) {
                    $resolvedParameters[] = [
                        'type' => 'default',
                        'value' => $parameter->getDefaultValue()
                    ];
                } else {
                    throw new Exception("Unable to autowire parameter: " . $paramName);
                }
            } elseif ($parameter->isOptional()) {
                $resolvedParameters[] = [
                    'type' => 'default',
                    'value' => $parameter->getDefaultValue()
                ];
            } else {
                throw new Exception("Unable to autowire parameter: " . $parameter->getName());
            }
        }

        return $resolvedParameters;
    }

    private function handleFactoryRecursively($definition)
    {
        if (isset($definition['factory'])) {
            $factory = $definition['factory'];
            $arguments = $this->resolveArgumentsRecursively($definition['arguments'] ?? []);
            $factoryClass = $factory[0];
            $factoryMethod = $factory[1];
            
            if (is_string($factoryClass)) {
                $factoryInfo = [
                    'type' => 'class',
                    'class' => $factoryClass,
                    'method' => $factoryMethod
                ];
            } else {
                $factoryInfo = [
                    'type' => 'service',
                    'name' => $factoryClass,
                    'class' => get_class($factoryClass),
                    'method' => $factoryMethod
                ];
            }

            // Attempt to determine the concrete class
            $concreteClass = $this->resolveConcreteClass($factoryClass, $factoryMethod, $arguments);
            if ($concreteClass) {
                $factoryInfo['concrete_class'] = $concreteClass;
            }

            return [
                'factory' => $factoryInfo,
                'arguments' => $arguments,
                'concrete_class' => $concreteClass
            ];
        }
        return null;
    }


    private function resolveConcreteClass($factoryClass, $factoryMethod, $arguments)
    {
        try {
            // Instantiate the factory
            if (is_string($factoryClass)) {
                $factory = new $factoryClass();
            } else {
                $factory = $factoryClass;
            }

            // Resolve arguments
            $resolvedArguments = $this->resolveReferences($arguments);

            // Call the factory method
            $result = call_user_func_array([$factory, $factoryMethod], $resolvedArguments);

            // Get the class name of the returned object
            if (is_object($result)) {
                return get_class($result);
            }
        } catch (Exception $e) {
            // Log the exception
            $this->logger->error("Error resolving concrete class: " . $e->getMessage());
        }

        // If we can't determine the concrete class, fall back to reflection
        try {
            $reflectionMethod = new ReflectionMethod(is_string($factoryClass) ? $factoryClass : get_class($factoryClass), $factoryMethod);
            $returnType = $reflectionMethod->getReturnType();
            if ($returnType && !$returnType->isBuiltin()) {
                return $returnType->getName();
            }
        } catch (ReflectionException $e) {
            $this->logger->error("Reflection error: " . $e->getMessage());
        }

        // If all else fails, return null
        return null;
    }

    public function autoDiscover(string $directory, string $namespace)
    {
        $discovery = new ServiceDiscovery($this);
        $services = $discovery->discover($directory, $namespace);

        foreach ($services as $id => $definition) {
            $this->set($id, $definition['class']);
            if (!empty($definition['arguments'])) {
                $this->arguments($id, $definition['arguments']);
            }
            if ($definition['autowire']) {
                $this->autowire($id);
            }
        }

        $this->logger->info(sprintf('Auto-discovered %d services in directory %s', count($services), $directory));
    }

   
}
