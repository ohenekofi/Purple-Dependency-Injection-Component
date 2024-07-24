<?php
namespace Purple\Core\Services;

use ReflectionClass;
use ReflectionMethod;
use Exception;
use Purple\Core\Services\Exception\ServiceNotFoundException;
use Purple\Core\Services\Exception\DependencyResolutionException;
use Symfony\Component\Yaml\Yaml;
use Purple\Core\Services\Container;
use Purple\Core\Services\ContainerConfigurator;
use Purple\Core\Services\{ConfigurationLoader, ScopeManager,ServiceFactory, DependencyResolver};

class DependencyGraph
{
    private $container;
    private DependencyResolver $dependencyResolver;
    private ServiceFactory $serviceFactory;
    private array $requestScopedServices = [];
    private array $circularDependencies = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->dependencyResolver = new DependencyResolver($this->container);
        $this->serviceFactory = new ServiceFactory($this->container);
    }

    public function detectCircularDependencies()
    {
        $this->circularDependencies = [];
        foreach ($this->container->dependencyGraph as $id => $definition) {
            
            $this->container->dependencyStack = [];
            $this->container->resolving = [];
            try {
                $this->resolveServiceDependencies($id);
                //print_r($id);
            } catch (CircularDependencyException $e) {
                
                $this->circularDependencies[$id] = $this->container->dependencyStack;
                //print_r($e);
            }
        }
    }

    
    private function resolveServiceDependencies($id)
    {
       
        if (!isset($this->container->dependencyGraph[$id])) {
            throw new ServiceNotFoundException("Service '$id' not found");
        }

        if (isset($this->container->resolving[$id])) {
            
            throw new CircularDependencyException("Circular dependency detected for service '$id'");
        }
       // print_r($this->container->dependencyStack);
        $this->container->resolving[$id] = true;
        $this->container->dependencyStack[] = $id;

        $definition = $this->container->dependencyGraph[$id];

        try {
            if (isset($definition['arguments'])) {
                //print_r($definition['arguments']);
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
            unset($this->container->resolving[$id]);
            array_pop($this->container->dependencyStack);
        }
    }

    private function handleCircularDependencies()
    {
        $message = "Circular dependencies detected:\n";
        foreach ($this->circularDependencies as $service => $dependencyChain) {
            $message .= "Service '$service': " . implode(' -> ', $dependencyChain) . " -> $service\n";
        }
        
        // Log the circular dependency details
        $this->container->get('logger')->error($message);
        
        // You can choose to throw an exception here if you want to prevent the application from starting
        // throw new CircularDependencyException($message);
        
        // Or you can just log the error and continue, allowing the application to run but with potential issues
        error_log($message);
    }



}