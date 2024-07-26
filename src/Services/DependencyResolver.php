<?php
namespace Purple\Core\Services;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use Attribute;
use Exception;
use Purple\Core\Services\Exception\ServiceNotFoundException;
use Purple\Core\Services\Exception\DependencyResolutionException;
use Purple\Core\Services\Container;
use Purple\Core\Services\Reference;
use stdClass;
use Inject;
//use Purple\Core\Services\ServiceFactory;

class DependencyResolver
{
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        
    }

    public function resolveDependencies($serviceName, $serviceConfig)
    {
        $resolvedConfig = $this->initializeResolvedConfig($serviceConfig);

        if (isset($serviceConfig['arguments'])) {
            $resolvedConfig['arguments'] = $this->resolveArgumentsRecursively($serviceConfig['arguments']);
        } elseif ($resolvedConfig['autowire'] && $resolvedConfig['class']) {
            $resolvedConfig['arguments'] = $this->autowireArgumentsRecursively($resolvedConfig['class']);
        } elseif ($resolvedConfig['annotwire']) {
            $resolvedConfig['arguments'] = $this->resolveAnnotwiredArguments($resolvedConfig['class']);
        }

        // Resolve method calls
        if (isset($serviceConfig['method_calls'])) {
            foreach ($serviceConfig['method_calls'] as $methodCall) {
                $methodName = $methodCall[0];
                $methodArguments = $methodCall[1] ?? [];

                if ($resolvedConfig['autowire'] && empty($methodArguments)) {
                    $methodArguments = $this->autowireMethodArgumentsRecursively($resolvedConfig['class'], $methodName);
                } elseif($resolvedConfig['annotwire'] && empty($methodArguments)){
                    $methodArguments = $this->autowireAnnotMethodArguments($resolvedConfig['class'], $methodName);
                }else {
                    $methodArguments = $this->resolveArgumentsRecursively($methodArguments);
                }

                $resolvedConfig['method_calls'][] = [$methodName, $methodArguments];
            }
        }

        // Handle factory and get concrete class
        if ($resolvedConfig['factory']) {
            $factoryInfo = $this->handleFactoryRecursively([
                'factory' => $resolvedConfig['factory'],
                'arguments' => $resolvedConfig['arguments'],
                'useWireType' => $resolvedConfig['useWireType']
            ]);
            $resolvedConfig['factory_info'] = $factoryInfo;
            
            // Use concrete class if available
            if (isset($factoryInfo['concrete_class'])) {
                $resolvedConfig['class'] = $factoryInfo['concrete_class'];
            }
        }

        return $resolvedConfig;
    }
    

    private function initializeResolvedConfig($serviceConfig)
    {
        return [
            'class' => $serviceConfig['class'] ?? null,
            'arguments' => [],
            'method_calls' => [],
            'tags' => $serviceConfig['tags'] ?? [],
            'factory' => $serviceConfig['factory'] ?? null,
            'implements' => $serviceConfig['implements'] ?? null,
            'scope' => $serviceConfig['scope'] ?? 'singleton',
            'asGlobal' => $serviceConfig['asGlobal'] ?? false,
            'asShared' => $serviceConfig['asShared'] ?? true,
            'lazy' => $serviceConfig['lazy'] ?? false,
            'autowire' => $serviceConfig['autowire'] ?? false,
            'annotwire' => $serviceConfig['annotwire'] ?? false,
            'useWireType' => $serviceConfig['useWireType'] ?? null,
        ];
    }

    private function handleFactoryRecursively($definition)
    {
        if (isset($definition['factory']) && !$definition['factory'] instanceof \Closure) {
            $factory = $definition['factory'];
            $factoryClass = $factory[0];
            $factoryMethod = $factory[1];
            $arguments = [];

            if (isset($definition['arguments']) && $definition['arguments'] === [] && $factoryMethod !== "" ) {
                # means which arent using the constructor of the factory but the method
                //let do a trigger here to choose whether wiretype is defined, else lets check for annotwire or autowire 
                if ($definition['useWireType'] === 'autowire') {
                    //use typehints
                   $arguments =  $this->buildfactoryparameters($factoryClass , $factoryMethod);

                   //$arguments = $this->resolveFactoryArguments($args);
                }elseif ($definition['useWireType'] === 'annotwire') {
                    //use annotation
                    $methodArguments = $this->autowireAnnotMethodArguments($factoryClass , $factoryMethod);
                }
            }else{
                $arguments = $this->resolveArgumentsRecursively($definition['arguments'] ?? []);
            }
            
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
            $concreteClass = $this->container->resolveConcreteClass($factoryClass, $factoryMethod, $arguments);
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

    private function autowireMethodArgumentsRecursively(string $className, string $methodName)
    {
        
        $parameters = $this->getMethodArguments($className, $methodName);
        return $this->resolveAutowiredParameters($parameters);
    }

    private function autowireAnnotMethodArguments(string $className, string $methodName)
    {
        if (!$this->isInstantiableClass($className)) {
            throw new Exception("Cannot autowire method arguments for non-instantiable class: $className");
        }

        $reflectionClass = new ReflectionClass($className);

        if (!$reflectionClass->hasMethod($methodName)) {
            throw new \InvalidArgumentException("Method $methodName does not exist in class $className");
        }

        $method = $reflectionClass->getMethod($methodName);

        
        if ($method->isAbstract()) {
            throw new Exception("Cannot autowire arguments for abstract method: $className::$methodName");
        }

        // Get method-level Inject annotations
        $methodAttributes = $method->getAttributes(\Inject::class);
        $injectValues = [];

        $injectAnnotations = [];
    
        foreach ($method->getParameters() as $parameter) {
            $attributes = $parameter->getAttributes();
            
            foreach ($attributes as $attribute) {
                // Debug output
                //echo "Parameter: " . $parameter->getName() . "\n";
                //echo "Attribute: " . $attribute->getName() . "\n";
                //echo "Arguments: " . print_r($attribute->getArguments(), true) . "\n\n";
                
                $injectAnnotations[$parameter->getName()] = $attribute->getArguments()[0];
               
            }
        }

        return $this->resolveAnnotedAutoWireParameters($injectAnnotations);
        
    }

    /*resolving properties via annotations*/
    private function resolvePropertyAnnotations(ReflectionClass $reflectionClass)
    {
        $annotations = [];
        
        foreach ($reflectionClass->getProperties() as $property) {
            $attributes = $property->getAttributes(Inject::class);
            
            if (!empty($attributes)) {
                $inject = $attributes[0];
                $annotations[$property->getName()] = $inject->getArguments()[0];
            }
        }
        
        return $annotations;
    }

    private function isInstantiableClass(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }
        
        $reflectionClass = new ReflectionClass($className);
        return !$reflectionClass->isAbstract() && !$reflectionClass->isInterface() && !$reflectionClass->isTrait();
    }

    private function resolveAutowiredParameters(array $parameters)
    {
        $resolvedParameters = [];
   
        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            
            if ($type && !$type->isBuiltin() ) {
                $typeName = $type->getName();

                //getting the service name using the type namespacename 
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
                if (isset($this->container->parameters[$paramName])) {
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

    private function resolveAnnotedAutoWireParameters(array $parameters)
    {
        $resolvedParameters = [];
        foreach ($parameters as $parameter) {

            if (strpos($parameter, '@') === 0) {
                // Starts with "@"
                $service = substr($parameter, 1);
                if ($service) {
                    $resolvedParameters[] = [
                        'type' => 'service_reference',
                        'id' => $service
                    ];
                }else {
                    //throw error
                }
                
            } elseif (strpos($parameter, '%') === 0 && strrpos($parameter, '%') === strlen($parameter) - 1) {
                // Starts and ends with "%"
                
                $paramarg = substr($parameter, 1, -1);  // Remove starting and ending "%"
                $resolvedParameters[] = [
                    'type' => 'parameter',
                    'name' => $paramarg,
                    'value' => $this->resolveParameter( $paramarg)
                ];
                
            } else {
                echo  $parameter;
                $resolvedParameters[] = [
                    'type' => 'parameter',
                    'name' => $parameter,
                    'value' => $parameter
                ];
            }

             
        }
        return $resolvedParameters;
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
 
        return $this->resolveAutowiredParameters($parameters);
    }

    private function resolveAnnotwiredArguments($className)
    {
        $reflectionClass = new ReflectionClass($className);
        $constructor = $reflectionClass->getConstructor();
        //$attributes = $constructor->getAttributes();
        $methodAttributes = $constructor->getAttributes(\Inject::class);

        $annotations = [];
        
        // Process constructor parameters
        foreach ($constructor->getParameters() as $parameter) {
            $attributes = $parameter->getAttributes();
            
            if (!empty($attributes)) {
                $inject = $attributes[0];
                $annotations[$parameter->getName()] = $inject->getArguments()[0];
            }
        }

        return $this->resolveAnnotedAutoWireParameters($annotations);
    }

    private function findServiceByType(string $type): string
    {
        if (isset($this->classTracker[$type])) {
            return $this->classTracker[$type];
        }else{
            foreach ($this->container->services as $serviceName => $serviceConfig) {
                if (isset($serviceConfig['implements']) && $serviceConfig['implements'] === $type) {
                    return $serviceName;
                }
                if (isset($serviceConfig['class']) && $serviceConfig['class'] === $type) {
                    return $serviceName;
                }
            }
            return null;
        }
       
    }

    public function resolveArgumentsRecursively(array $arguments)
    {
        $resolvedArguments = [];
       //_r($arguments);
       
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
                    'value' => $this->container->getParameter($matches[1])
                ];
            } elseif (is_string($argument) && preg_match('/^\%(.+)\%$/', $argument, $matches)) {
                $resolvedArguments[$key] = [
                    'type' => 'parameter',
                    'name' => $matches[1],
                    'value' => $this->container->getParameter($matches[1])
                ];
            } else {
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
        }elseif(is_string($value) && !strpos($value, '%') === 0 && !substr($value, -1) === '%'){
            //mostly will be used by annotations
            if (isset($this->parameters[$value])) {
                return $this->parameters[$value] ?? $value;
            }
        }
        return $value;
    }


    private function resolveAnnotwiredService($id)
    {
        $definition = $this->services[$id];
        $class = $definition['class'];
        $reflectionClass = new ReflectionClass($class);

        // Resolve constructor injection
        $constructor = $reflectionClass->getConstructor();
        $constructorParams = [];
        if ($constructor) {
            foreach ($constructor->getParameters() as $param) {
                $constructorParams[] = $this->resolveAnnotwiredParameter($param);
            }
        }

        // Create the instance
        $instance = $reflectionClass->newInstanceArgs($constructorParams);

        // Resolve property injection
        foreach ($reflectionClass->getProperties() as $property) {
            if ($property->isPublic() && $property->getAttributes('Inject')) {
                $value = $this->resolveAnnotwiredProperty($property);
                $property->setValue($instance, $value);
            }
        }

        // Resolve method injection
        foreach ($reflectionClass->getMethods() as $method) {
            if ($method->getAttributes('Inject')) {
                $params = [];
                foreach ($method->getParameters() as $param) {
                    $params[] = $this->resolveAnnotwiredParameter($param);
                }
                $method->invokeArgs($instance, $params);
            }
        }

        return $instance;
    }

    private function resolveAnnotwiredParameter(ReflectionParameter $param)
    {
        $type = $param->getType();
        if ($type && !$type->isBuiltin()) {
            return $this->get($type->getName());
        }
        // Handle other cases (primitives, etc.)
        return null;
    }

    private function resolveAnnotwiredProperty(ReflectionProperty $property)
    {
        $type = $property->getType();
        if ($type && !$type->isBuiltin()) {
            return $this->get($type->getName());
        }
        // Handle other cases
        return null;
    }

    private function getMethodArguments($className, string $methodName) 
    {
        $reflectionClass = new ReflectionClass($className);
        $method = $reflectionClass->getMethod($methodName);
        $parameters = $method->getParameters();
        
        if ($method->isAbstract()) {
            throw new Exception("Cannot autowire arguments for abstract method: $className::$methodName");
        }

        return $parameters = $method->getParameters();

    }


    private function buildfactoryparameters($className, string $methodName){

        $parameters = $this->getMethodArguments($className, $methodName);
        $args = [];
        foreach ($parameters as  $param) {
            $type = $param->getType();
            $typeName = $type ? $type->getName() : 'mixed';
            
            $isScalar = in_array($typeName, ['int', 'float', 'string', 'bool']);
            $defaultValue = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;

            $arg = [
                'type' => $isScalar ? 'parameter' : 'service_reference',
                'id' => $isScalar ? "": $this->findServiceByType($type->getName()),
                'name' =>  $isScalar ? $param->getName() : $this->findServiceByType($type->getName()),
                'value' => $defaultValue
            ];

            //$args[] = $isScalar ? '%'.$param->getName().'%' : '@' . $param->getName();
            $args[] = $arg; 
        }
        return $args;
    }

}
