<?php
namespace Purple\Core\Container;

class LazyPropertyProxy
{
    private $service;
    private $proxiedProperties;
    private $initializedProperties = [];

    public function __construct($service, array $proxiedProperties)
    {
        $this->service = $service;
        $this->proxiedProperties = $proxiedProperties;
    }

    public function __get($property)
    {
        if (in_array($property, $this->proxiedProperties) && !isset($this->initializedProperties[$property])) {
            $this->initializeProperty($property);
        }

        return $this->service->$property;
    }

    public function __set($property, $value)
    {
        $this->service->$property = $value;
    }

    private function initializeProperty($property)
    {
        $reflectionClass = new ReflectionClass($this->service);
        $reflectionProperty = $reflectionClass->getProperty($property);
        $reflectionProperty->setAccessible(true);

        $initializer = $reflectionProperty->getInitializer()->getClosure($this->service);
        $reflectionProperty->setValue($this->service, $initializer());

        $this->initializedProperties[$property] = true;
    }
}