<?php

namespace Purple\Core\Container;

use ReflectionClass;
use ReflectionException;
use InvalidArgumentException;
use RuntimeException;
use Purple\Core\Container\LazyPropertyProxy;
use Purple\Core\Container\LazyProxy;

class ServiceNotFoundException extends InvalidArgumentException {}
class ContainerException extends RuntimeException {}

class Container
{
    private $services = [];
    private $definitions = [];
    private $parameters = [];
    private $interfaces = [];
    private $env = [];
    private $aliases = [];
    private $resolved = [];

    public function __construct(array $definitions, $envPath = null)
    {
        $this->parameters = $definitions['parameters'] ?? [];
        $this->definitions = $definitions['services'] ?? [];
        $this->interfaces = $definitions['interfaces'] ?? [];

        if ($envPath) {
            $this->loadEnv($envPath);
        }

        $this->inheritDefinitions();
        $this->registerAliases();
    }

    private function loadEnv($envPath)
    {
        if (!file_exists($envPath)) {
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $this->env[trim($name)] = trim($value);
        }
    }

    private function getEnv($name)
    {
        if (!isset($this->env[$name])) {
            throw new InvalidArgumentException("Environment variable $name is not defined.");
        }
        return $this->env[$name];
    }

    public function get($id)
    {
        if (isset($this->resolved[$id])) {
            return $this->resolved[$id];
        }

        if (isset($this->aliases[$id])) {
            $id = $this->aliases[$id];
        }

        if (isset($this->interfaces[$id])) {
            $id = $this->interfaces[$id];
        }

        if (!isset($this->definitions[$id])) {
            throw new ServiceNotFoundException("Service $id is not defined.");
        }

        if (isset($this->services[$id])) {
            return $this->services[$id];
        }

        $this->checkForCircularReference($id);

        $this->resolved[$id] = $this->createService($id);

        return $this->resolved[$id];
    }

    private function createService($id)
    {
        $definition = $this->definitions[$id];

        if (isset($definition['visibility']) && $definition['visibility'] === 'private') {
            throw new ServiceNotFoundException("Service $id is defined as private and cannot be accessed directly.");
        }

        if (isset($definition['factory'])) {
            $service = $this->resolveFactory($definition['factory']);
            $shared = $this->isShared($definition);
        } elseif (isset($definition['class'])) {
            $service = $this->resolveServiceClass($definition);
            $shared = $this->isShared($definition);
        } else {
            throw new ContainerException("Service definition for $id is invalid.");
        }

        if (isset($definition['lazy'])) {
            $lazyStrategy = $definition['lazy'];

            switch ($lazyStrategy) {
                case 'method':
                    $service = $this->createLazyMethodProxy($service, $definition);
                    break;
                case 'property':
                    $service = $this->createLazyPropertyProxy($service, $definition);
                    break;
                case true:
                    $service = $this->createLazyClassInitialization($service, $definition);
                    break;
                default:
                    throw new ContainerException("Invalid lazy loading strategy: $lazyStrategy");
            }
        }

        if (isset($definition['calls'])) {
            $this->applyMethodCalls($service, $definition['calls']);
        }

        if ($shared) {
            $this->services[$id] = $service;
        }

        return $service;
    }

    private function createLazyClassInitialization($service, $definition)
    {
        $class = $definition['class'] ?? null;
        $arguments = $definition['arguments'] ?? [];
        $arguments = $this->resolveArguments($arguments);
        return new LazyProxy($class, $arguments);
    }

    private function createLazyMethodProxy($service, $definition)
    {
        $method = $definition['lazy_method'] ?? '__invoke';
        return new LazyProxy($service, $method);
    }

    private function createLazyPropertyProxy($service, $definition)
    {
        $properties = $definition['lazy_properties'] ?? [];
        return new LazyPropertyProxy($service, $properties);
    }

    private function resolveServiceClass($definition)
    {
        $class = $definition['class'];

        if ($this->isAbstractOrInterface($class)) {
            if (isset($definition['factory'])) {
                return $this->resolveFactory($definition['factory']);
            } else {
                throw new ContainerException("Cannot instantiate abstract class or interface $class without a factory.");
            }
        }

        $arguments = isset($definition['arguments']) ? $this->resolveArguments($definition['arguments']) : $this->autowireArguments($class);

        return new $class(...$arguments);
    }

    private function resolveFactory($factory)
    {
        if (is_callable($factory)) {
            return call_user_func($factory);
        }

        if (is_array($factory)) {
            if (is_string($factory[0]) && class_exists($factory[0])) {
                $class = new ReflectionClass($factory[0]);

                if (!$class->hasMethod($factory[1])) {
                    throw new ContainerException("Factory method {$factory[1]} does not exist on class {$factory[0]}.");
                }

                return call_user_func([$class->newInstance(), $factory[1]]);
            }

            if (is_string($factory[0]) && strpos($factory[0], '@') === 0) {
                $service = $this->get(substr($factory[0], 1));

                if (method_exists($service, $factory[1])) {
                    return call_user_func([$service, $factory[1]]);
                } else {
                    throw new ContainerException("Factory method {$factory[1]} does not exist on service {$factory[0]}.");
                }
            }
        }

        throw new ContainerException("Factory definition is not valid.");
    }

    private function autowireArguments($class)
    {
        $reflectionClass = new ReflectionClass($class);
        $constructor = $reflectionClass->getConstructor();

        if (!$constructor) {
            return [];
        }

        $parameters = $constructor->getParameters();
        $arguments = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type && !$type->isBuiltin()) {
                $arguments[] = $this->get($type->getName());
            } else {
                $defaultValue = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
                $arguments[] = $defaultValue;
            }
        }

        return $arguments;
    }

    private function resolveArguments(array $arguments)
    {
        return array_map(fn($arg) => $this->resolveArgument($arg), $arguments);
    }

    private function resolveArgument($argument)
    {
        if (is_string($argument)) {
            if (strpos($argument, '@') === 0) {
                return $this->get(substr($argument, 1));
            } elseif (strpos($argument, '%') === 0 && substr($argument, -1) === '%') {
                return $this->getParameter(trim($argument, '%'));
            } elseif (strpos($argument, '$') === 0 && substr($argument, -1) === '$') {
                return $this->getEnv(trim($argument, '$'));
            }
        }

        return $argument;
    }

    public function getParameter($name)
    {
        if (!array_key_exists($name, $this->parameters)) {
            throw new InvalidArgumentException("Parameter $name is not defined.");
        }
        return $this->parameters[$name];
    }

    private function isShared($definition)
    {
        $scope = $definition['scope'] ?? 'singleton';
        return $scope === 'singleton';
    }

    private function isAbstractOrInterface($class)
    {
        try {
            $reflection = new ReflectionClass($class);
            return $reflection->isAbstract() || $reflection->isInterface();
        } catch (ReflectionException $e) {
            throw new ContainerException("Error resolving class $class: " . $e->getMessage());
        }
    }

    private function inheritDefinitions()
    {
        foreach ($this->definitions as $id => &$definition) {
            if (isset($definition['extends'])) {
                $parent = $definition['extends'];
                if (!isset($this->definitions[$parent])) {
                    throw new ContainerException("Parent service definition $parent does not exist.");
                }

                $parentDefinition = $this->definitions[$parent];
                $definition = array_merge($parentDefinition, $definition);
            }
        }
    }

    private function registerAliases()
    {
        foreach ($this->definitions as $id => $definition) {
            if (isset($definition['alias'])) {
                $this->aliases[$definition['alias']] = $id;
            }
        }
    }

    private function applyMethodCalls($service, $calls)
    {
        foreach ($calls as $call) {
            $method = $call[0];
            $arguments = isset($call[1]) ? $this->resolveArguments($call[1]) : [];
            call_user_func_array([$service, $method], $arguments);
        }
    }

    private function checkForCircularReference($id)
    {
        if (isset($this->resolved[$id])) {
            throw new ContainerException("Circular reference detected for service $id.");
        }
    }
}
