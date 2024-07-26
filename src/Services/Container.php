<?php
namespace Purple\Core\Services;

use Exception;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\Finder;
use ReflectionClass;
use ReflectionMethod;
use Purple\Core\Services\Exception\ServiceAccessException;
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
use Purple\Core\Services\Interface\MiddlewareInterface;
use Purple\Core\Services\{
    ConfigurationLoader, 
    ScopeManager,
    ServiceFactory, 
    DependencyResolver, 
    DependencyGraph,
    ServiceTracking,
    AnnotationParser
};

class Container
{
    const SCOPE_SINGLETON = 'singleton';
    const SCOPE_REQUEST = 'request';
    const SCOPE_PROTOTYPE = 'prototype';
    const SCOPE_PROXY = 'proxy';
    const SCOPE_PRIVATE = "private";

    public array $parameters = [];
    private $compilerPasses = [];
    public array $services = [];
    private array $aliases = [];
    private array $tags = [];
    private array $middlewares = [];
    public array $dependencyGraph = [];
    public array $dependencyGraphCache = [];
    public array $resolvedServices = [];
    public array $dependencyStack = [];
    public array $resolving = [];
    private array $instances = [];
    private $logFilePath;
    public $tagsByName = [];
    private CacheInterface $cache;
    public Logger $logger;
    public array $definitions = [];
    private array $decorators = [];
    private array $globalMiddleware = [];
    private array $serviceMiddleware = [];
    public $setAsGlobal = null;
    public $setAutowire = null;
    public $wireType = '';
    public $setAnnotwire = null;
    public $classTracker = [];

    public DependencyResolver $dependencyResolver;
    private ServiceFactory $serviceFactory;
    private ConfigurationLoader $configureLoader;
    private ScopeManager $scopeManager;
    private DependencyGraph $dependencyGraphClass;
    private ServiceTracking $serviceTracking;
    private AnnotationParser $annotationParser;
    

    public function __construct(string $logFilePath,  CacheInterface $cache)
    {
      $this->logger = new Logger('container');
      $this->logger->pushHandler(new StreamHandler($logFilePath, Logger::DEBUG));
      $this->cache = $cache;
      $this->dependencyResolver = new DependencyResolver($this);
      $this->serviceFactory = new ServiceFactory($this);
      $this->configureLoader = new ConfigurationLoader($this);
      $this->scopeManager = new ScopeManager($this);
      $this->dependencyGraphClass = new DependencyGraph($this);
      $this->serviceTracking = new ServiceTracking($this);
      $this->annotationParser = new AnnotationParser($this->logger, $this);
    }

    public function loadEnv($file)
    {
        $this->configureLoader->loadEnv($file);
    }

    public function loadConfigurationFromFile($filePath){
        $this->configureLoader->loadConfigurationFromFile($filePath);   
    }

    public function enableServiceCaching()
    {
        $cachedGraph = $this->cache->get('dependency_graph');

        if ($cachedGraph) {
            $this->definitions = $cachedGraph;
            $this->logger->info('Loaded services from cache.');
        } else {
            $this->logger->info('Building dependency graph and caching services.');
            //MUST BE ACTIVATED FOR CACHING
            $this->cache->set('dependency_graph', $this->dependencyGraphCache);
        }
    }

    public function getCache()
    {
        return $cachedGraph = $this->cache->get('dependency_graph');

    }

    public function getDefinitionforCache(){
        return $this->definitions;
    }

    public function addCompilerPass(CompilerPassInterface $pass)
    {
        $this->compilerPasses[] = $pass;
    }

    public function compile()
    {
        // Sort compiler passes by priority
        usort($this->compilerPasses, function ($a, $b) {
            return $b->getPriority() - $a->getPriority();
        });

        foreach ($this->compilerPasses as $pass) {
            $pass->process($this);
        }
    }

    public function getDefinition($serviceName)
    {
        // Return the service definition or an error if it does not exist
        if (!isset($this->services[$serviceName])) {
            throw new ServiceNotFoundExceptionException("Service not found: " . $serviceName);
        }
        return $this->services[$serviceName];
    }

    public function getDefinitions()
    {
        // Return the service definition or an error if it does not exist
        if (!isset($this->services)) {
            throw new ServiceNotFoundException("Services not found");
        }
        return $this->services;
    }

    public function setAlias($alias, $service)
    {
        $this->aliases[$alias] = $service;
        $this->asGlobal($service, true); // Make alias public by default
        return $this;
    }
   
    public function set($id, $class)
    {
       // $this->services[$id] = ['class' => $class];
        // for searching for services with their class names
       // $this->classTracker[$class] = [$id];


        if ($class instanceof \Closure) {
            $this->services[$id] = ['factory' => $class];
        } elseif (is_callable($class)) {
            $this->services[$id] = ['factory' => $class];
        } else {
            $this->services[$id] = ['class' => $class];
        }
         // for searching for services with their class names
         if (is_string($class)) {
            $this->classTracker[$class] = [$id];
        }
        
        if ($this->setAutowire !== null &&  $this->setAutowire === true && $this->wireType === "hints") {
            $this->services[$id]['autowire'] = true;
        }
        if ($this->setAnnotwire !== null &&  $this->setAnnotwire === true && $this->wireType === "annots") {
            $this->services[$id]['annotwire'] = true;
        }
        if ($this->setAsGlobal !== null &&  $this->setAsGlobal === true ) {

            $this->services[$id]['asGlobal'] = true ;
        }
        
        return $this;
        
    }
    
    public function has($id): bool
    {
        return isset($this->services[$id]);
    }

    //not fully implemented
    private function updateDependencyGraph($id)
    {
        if (!isset($this->services[$id])) {
            throw new ServiceNotFoundException("Service '$id' not found");
        }
        
        $serviceConfig = $this->services[$id];
        $this->dependencyGraph[$id] = $this->dependencyResolver->resolveDependencies($id, $serviceConfig);
        
        // Optionally, update cache if you're using caching
        if (isset($this->cache)) {
            $this->cache->set('dependency_graph', $this->dependencyGraph);
        }
        
        $this->logger->info("Dependency graph updated for service: $id");
    }

    public function runGarbageCollection(){
        $this->serviceTracking->runGarbageCollection();
    }

    public function addTag($id, $attributes = [])
    {
        $this->tags[$id] = $attributes;
        foreach ($attributes as $tag ) {
            if (!isset($this->tagsByName[$tag])) {
                $this->tagsByName[$tag] = [];
            }
            array_push($this->tagsByName[$tag], $id);
        }
    }

    public function findTaggedServiceIds($tagName)
    {
        return $this->tagsByName[$tagName] ?? [];
    }

    public function getServiceTags($serviceName)
    {
        return $this->tags[$serviceName] ?? [];
    }

    public function getByTag($tag) 
    {
        if (!isset($this->tags[$tag])) {
            return [];
        }
    
        $services = [];
        foreach ($this->tags[$tag] as $id) {
            $services[] = $this->get($id);
        }
    
        return $services;
    }

    public function addGlobalMiddleware(MiddlewareInterface $middleware): self
    {
        $this->globalMiddleware[] = $middleware;
        return $this;
    }

    public function addServiceMiddleware(string $serviceId, MiddlewareInterface $middleware): self
    {
        if (!isset($this->serviceMiddleware[$serviceId])) {
            $this->serviceMiddleware[$serviceId] = [];
        }
        $this->serviceMiddleware[$serviceId][] = $middleware;
        return $this;
    }

    private function applyMiddleware($service, string $id): object
    {
        $middleware = array_merge(
            $this->globalMiddleware,
            $this->serviceMiddleware[$id] ?? []
        );

        $runner = function ($service) use (&$runner, &$middleware, $id) {
            if (empty($middleware)) {
                return $service;
            }

            $next = array_shift($middleware);
            return $next->process($service, $id, function ($service) use ($runner) {
                return $runner($service);
            });
        };

        return $runner($service);
    }

    public function scope($id, $scope)
    {
        if (!in_array($scope, [self::SCOPE_SINGLETON, self::SCOPE_REQUEST, self::SCOPE_PROTOTYPE, self::SCOPE_PROXY, self::SCOPE_PRIVATE])) {
            throw new ServiceAccessException("Invalid scope: $scope");
        }
        $this->services[$id]['scope'] = $scope;
        return $this;
    }

    public function asGlobal($id,bool $state)
    {
        //as private or public (bool true/false)--defaults to false private
        $st = ($state === "" || $state === null )? false : $state;
        if ($this->setAsGlobal !== null &&  $this->setAsGlobal === true ) {

            $this->services[$id]['asGlobal'] = true ;
        }else{
            $this->services[$id]['asGlobal'] =  $st;
        }
       
        return $this;
    }

    public function asShared($id, bool $state){
        // as singleton or prototype(new instance per req) (bool true/false)--defaults to true singleton
        $st = ($state === "" || $state === null ) ? true : $state;
        $this->services[$id]['asShared'] =  $st;
        return $this;
    }

    public function setAsGlobal(bool $state){
        // Make services public (optional, depending on your needs)
        $this->setAsGlobal= $state;
    }

    public function setAutowire(bool $state){
        // Make services automatically autowire using typehints
        $this->setAutowire = $state;
    }

    public function setAnnotwire(bool $state){
        // Make services automatically autowire using annotations
        $this->setAnnotwire = $state;
    }

    public function wireType(string $type){
        // choosing between annotwire and autowire
        $typeArray = array('hints', 'annots');
        if (in_array(strtolower($type),  $typeArray)) {
            $this->wireType = strtolower($type);
        }else{
            throw new ServiceAccessException("autowire type $type not found");
        }
    }

    public function decorate($id, $decoratorClass, $innerServiceId = null)
    {
        $this->decorators[$id] = [
            'class' => $decoratorClass,
            'inner' => $innerServiceId ?? $id . '.inner'
        ];
        return $this;
    }

    private function resolveDecorator($id)
    {
        $decoratorConfig = $this->decorators[$id];
        $innerServiceId = $decoratorConfig['inner'];

        $decoratorClass = $decoratorConfig['class'];
        $decorator = new $decoratorClass($this->get($innerServiceId));

        return $decorator;
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
            throw new ServiceNotFoundException("Service $name not found");
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
        if ($this->setAutowire !== null &&  $this->setAutowire === true && $this->wireType === "hints") {
            $this->services[$id]['autowire'] = true;
            $this->services[$id]['useWireType'] = "autowire";
        }else{
            $this->services[$id]['autowire'] = true;
            $this->services[$id]['useWireType'] = "autowire";
        }
        
        return $this;
    }

    public function annotwire($id)
    {
        if ($this->setAnnotwire !== null &&  $this->setAnnotwire === true && $this->wireType === "annots") {
            $this->services[$id]['annotwire'] = true;
            $this->services[$id]['useWireType'] = "annotwire";
        }else{
            $this->services[$id]['annotwire'] = true;
            $this->services[$id]['useWireType'] = "annotwire";
        }
        return $this;
    }

    public function getParameter($name)
    {   
        if (!isset($this->parameters[$name])) {
            //throw new Exception("Parameter '$name' not found");
            return $name;
        }
        return $this->parameters[$name];
    }

    public function getService($id,bool $directRequest )
    {
        $definition = $this->container->dependencyGraph[$id] ?? null;

        if (isset($this->aliases[$id])) {
            $id = $this->aliases[$id];
        }

        if (!isset($this->services[$id])) {
            throw new ServiceNotFoundException("Service '$id' not found");
        }

        if (!isset($this->services[$id]['instance'])) {
            $this->services[$id]['instance'] = $this->createService($id,  $directRequest);
        }

        return $this->services[$id]['instance'];
    }

    public function createService($id,bool $directRequest ){
        
        $definition = $this->dependencyGraph[$id];
        $asShared = isset($definition['asShared']) ?? true;

        //updates the instances with the new instatiated single object
        //means it was not instantitated via instances
        // Check if the service should be lazy loaded

        if (isset($definition['factory'])) {
            $factory = $definition['factory'];
            if ($factory instanceof \Closure || is_callable($factory)) {
                //print_r($definition['factory']);
               return $service = $factory($this);
            }
        }

        $service = $this->serviceFactory->createService($id, $directRequest);
        
        if ($asShared === true) {
            $this->instances[$id] = $service;
        }

        return $service;
    }

    private function createLazyProxy($id, $definition)
    {
        return new LazyServiceProxy($this, $id, $definition);
    }

    public function createRealService($id)
    {
        if (!isset($this->dependencyGraph[$id])) {
            throw new ServiceNotFoundException("Service '$id' not found");
        }

        $definition = $this->dependencyGraph[$id];
        return $this->instantiateService($definition);
    }

    private function instantiateService($definition)
    {
        // Handle factory creation
        if (isset($definition['factory_info'])) {
            $factory = $definition['factory_info']['factory'];
            $arguments = $this->serviceFactory->resolveReferences($definition['factory_info']['arguments']);
            
            if ($factory['type'] === 'class') {
                $factoryInstance = new $factory['class']();
            } else {
                $factoryInstance = $this->get($factory['name']);
            }

            $instance = call_user_func_array([$factoryInstance, $factory['method']], $arguments);
        } else {
            // Create the service instance
            $arguments = $this->serviceFactory->resolveReferences($definition['arguments']);
            $reflectionClass = new ReflectionClass($definition['class']);
            $instance = $reflectionClass->newInstanceArgs($arguments);
        }

        // Handle method calls
        if (!empty($definition['method_calls'])) {
            foreach ($definition['method_calls'] as $methodCall) {
                $method = $methodCall[0];
                $methodArguments = $this->serviceFactory->resolveReferences($methodCall[1]);
                call_user_func_array([$instance, $method], $methodArguments);
            }
        }

        return $instance;
    }


    public function get($id,$checkGlobalflag = true)
    {
        $cache = $this->getCache();
        //print_r($cache);
        if (isset($this->aliases[$id])) {
            $id = $this->aliases[$id];
        }
    
        if (isset($this->decorators[$id])) {
            $this->resolveDecorator($id);
        }

        $definition = [];
        //get from cache
        if (!isset($cache[$id])) {
            if (isset($this->dependencyGraph[$id])) {
                $definition = $this->dependencyGraph[$id];

                //throw new ServiceNotFoundException("Service '$id' not found");
            }elseif (!isset($this->dependencyGraph[$id])) {
                throw new ServiceNotFoundException("Service '$id' not found");
            }
        }else{
            $definition = $this->dependencyGraphCache[$id];
        }
    

      //  $definition = $this->dependencyGraph[$id];

        if ($definition['lazy'] ?? false) {
            $lazyService  =  $this->createLazyProxy($id, $definition);
             return $lazyService->getInstance();
        }

        $asShared = $definition['asShared'];
        $asGlobal = $definition['asGlobal'];

        if($asGlobal !== true && $checkGlobalflag === true){
            throw new ServiceAccessException("Service '$id' is not accessible externally");
        }

        // Check if the service is already instantiated (for singleton scope)
        if ($asShared === true && isset($this->instances[$id])) {
            return $this->applyMiddleware($this->instances[$id], $id);
        }
        
        $this->resolving[$id] = true;
        $this->dependencyStack[] = $id;

        try {
            $service = $this->scopeManager->resolveService($id, true);
            return $this->applyMiddleware($service, $id);
        } catch (ServiceAccessException $e) {
            throw new ServiceAccessException("Service '$id' is not accessible externally");
        }
    }

    public function generateDependencyGraph()
    {   
        foreach ($this->services as $serviceName => $serviceConfig) {
            
            if(isset($serviceConfig['factory']) && !$serviceConfig['factory'] instanceof \Closure){
                //print_r($serviceConfig);
                $this->dependencyGraphCache[$serviceName] = $this->dependencyResolver->resolveDependencies($serviceName, $serviceConfig);
            }
            $this->dependencyGraph[$serviceName] = $this->dependencyResolver->resolveDependencies($serviceName, $serviceConfig);
        }
      
    }

    public function initiateDependancyGraph()
    {
        $this->generateDependencyGraph();
        $this->dependencyGraphClass->detectCircularDependencies();

        if (!empty($this->dependencyGraphClass->circularDependencies)) {
            $this->dependencyGraphClass->handleCircularDependencies();
        }
        //load all singleton services 
        $this->loadAllServices();
    }


    public function loadAllServices()
    {
        foreach ($this->dependencyGraph as $id => $definition) {
            
            //ensuring lazy taggged instances arent created
            $lazycheck = $definition['lazy'] ?? false;
            if ( $lazycheck=== false) {
                // Only instantiate if not already instantiated
                if (!isset($this->instances[$id])) {
                    $this->instances[$id] = $this->createService($id, true);
                }
            }
        }
        
        
        $this->logger->info(sprintf('Loaded %d singleton services', count($this->instances)));
    }

    public function resolveConcreteClass($factoryClass, $factoryMethod, $arguments)
    {
        try {
            // Instantiate the factory
            if (is_string($factoryClass)) {
                $factory = new $factoryClass();
            } else {
                $factory = $factoryClass;
            }

            $resolvedArguments = $this->serviceFactory->resolveReferences($arguments);
            
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

    public function handleFactoryRecursively($definition)
    {
        if (isset($definition['factory'])) {
            $factory = $definition['factory'];
            $arguments = $this->dependencyResolver->resolveArgumentsRecursively($definition['arguments'] ?? []);
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

    public function bindIf($abstract, $concrete = null): void
    {
        if (!$this->has($abstract)) {
            if (is_null($concrete)) {
                $concrete = $abstract;
            }
            $this->set($abstract, $concrete);
        }
    }

    public function callable($abstract, callable $factory): void
    {
        $this->set($abstract, $factory);
        $this->factory($abstract, function($container) use ($factory) {
            return $factory($container);
        });
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
            if ($definition['tags']) {
                $this->addTag($id,$definition['tags'] );
            }
        }

        $this->initiateDependancyGraph();

        $this->logger->info(sprintf('Auto-discovered %d services in directory %s', count($services), $directory));
    }

    public function annotationDiscovery(array $config)
    {
        foreach ($config['namespace'] as $namespace => $settings) {
            $finder = new Finder();
            $finder->files()->in($settings['resource'])->name('*.php');

            if (isset($settings['exclude'])) {
                foreach ($settings['exclude'] as $exclude) {
                    $finder->notPath($exclude);
                }
            }

            foreach ($finder as $file) {
                $className = $this->getFullyQualifiedClassName($file, $namespace);
                if ($className && $this->isInstantiableClass($className)) {
                    $this->annotationParser->parse($className);
                } else {
                    $this->logger->info("Skipping non-concrete class: $className");
                }
            }
        }
        $this->initiateDependancyGraph();
       
    }

    private function getFullyQualifiedClassName(\SplFileInfo $file, string $namespace): string
    {
        $className = $namespace . '\\' . str_replace(
            ['/', '.php'],
            ['\\', ''],
            $file->getRelativePathname()
        );

        return $className;
    }

    private function isInstantiableClass(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        $reflection = new ReflectionClass($className);
        return !$reflection->isAbstract() && !$reflection->isInterface() && !$reflection->isTrait();
    }


}

