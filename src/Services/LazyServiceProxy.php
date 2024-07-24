<?php
namespace Purple\Core\Services;
use Purple\Core\Services\Container;

class LazyServiceProxy {
    private $serviceId;
    private $container;
    private $instance;
    private $definition;

    public function __construct(Container $container, $serviceId, $definition) {
        $this->container = $container;
        $this->serviceId = $serviceId;
        $this->definition = $definition;
    }

    public function getInstance() {
        if (!$this->instance) {
            $this->instance = $this->container->createRealService($this->serviceId);
        }
        return $this->instance;
    }

    public function __call($method, $arguments) {
        return call_user_func_array([$this->getInstance(), $method], $arguments);
    }

    public function __get($name) {
        return $this->getInstance()->$name;
    }

    public function __set($name, $value) {
        $this->getInstance()->$name = $value;
    }

}
