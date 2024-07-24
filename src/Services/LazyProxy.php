<?php
namespace Purple\Core\Services;

class LazyProxy
{
    private $container;
    private $serviceName;
    private $realInstance;

    public function __construct($container, $serviceName)
    {
        $this->container = $container;
        $this->serviceName = $serviceName;
    }

    public function __call($method, $args)
    {
        if (!$this->realInstance) {
            $this->realInstance = $this->container->get($this->serviceName, true); // Internal call
        }

        return call_user_func_array([$this->realInstance, $method], $args);
    }

    // Handle property access and other magic methods if necessary...
}
