<?php
namespace Purple\Core\Services;

use ReflectionClass;
use ReflectionMethod;
use Purple\Core\Services\Exception\ServiceNotFoundException;
use Purple\Core\Services\Exception\DependencyResolutionException;
use Purple\Core\Services\Container;
use Purple\Core\Services\DependencyResolver;

class ServiceFactory
{
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
       
    }

    public function createService($id , $directRequest = true)
    {
        if (!isset($this->container->dependencyGraph[$id])) {
            throw new ServiceNotFoundException("Service '$id' not found");
        }

        $definition = $this->container->dependencyGraph[$id];

        $asGlobal =$definition['asGlobal'];

        if (!$asGlobal && !$directRequest) {
           echo "allowed";
        }
        // Handle factory creation
        if (isset($definition['factory_info'])) {
            $factory = $definition['factory_info']['factory'];
            $arguments = $this->resolveReferences($definition['factory_info']['arguments']);
            
            if ($factory['type'] === 'class') {
                $factoryInstance = new $factory['class']();
            } else {
                $factoryInstance = $this->container->get($factory['name'], false );
            }
            $resolvedArgs = $this->resolveArguments($arguments);

            $instance = call_user_func_array([$factoryInstance, $factory['method']], $resolvedArgs);
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
                call_user_func_array([$instance, $method], $methodArguments);
            }
        }

        //means it cant be called out the container via get only internal use
        if ($definition['asGlobal'] === true) {
            //$this->container->resolvedServices[$id] = $instance;
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

    private function resolveArguments(array $arguments)
    {
        $resolvedArguments = [];

        foreach ($arguments as $key => $argument) {
            if ($argument instanceof Reference) {
                $resolvedArguments[$key] = $this->container->getService($argument->getId(),);
            } elseif (is_string($argument) && strpos($argument, '@') === 0) {
                // Handle string references (e.g., '@service_name')
                $serviceId = substr($argument, 1);
                $resolvedArguments[$key] = $this->container->getService($serviceId);
            } elseif (is_array($argument)) {
                $resolvedArguments[$key] = $this->container->resolveArguments($argument);
            }  elseif (is_string($argument) && preg_match('/^\$(.+)\$$/', $argument, $matches)) {
                $resolvedArguments[$key] = $this->container->getParameter($matches[1]);
            }elseif (is_string($argument) && preg_match('/^%(.*?)%$/', $argument, $matches)) {
                $resolvedArguments[$key] = $this->container->getParameter($matches[1]);
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

    public function resolveReferences($arguments)
    {
        $resolved = [];
        foreach ($arguments as $arg) {
            if (isset($arg['type']) && $arg['type'] === 'service_reference') {
                $resolved[] = $this->container->get($arg['id'], false);
            } elseif (isset($arg['type']) && $arg['type'] === 'parameter') {
                //$resolved[] = $arg['value'];
                //echo   $this->getParameter($arg['name']);
                $resolved[] = $this->container->getParameter($arg['name']);
            } elseif (isset($arg['type']) && $arg['type'] === 'default') {
                if(empty($arg['value'])){
                    $resolved[] = $arg['value'];
                }else{
                    $resolved[] = $this->container->getParameter($arg['value']);
                }
                
            }else {
                $resolved[] = $arg;
            }
        }

        return $resolved;
    }


 

}