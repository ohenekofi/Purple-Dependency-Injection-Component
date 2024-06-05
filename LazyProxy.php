<?php
namespace Purple\Core\Container;


class LazyProxy
{
    private $service;
    private $method;

    public function __construct($service, string $method)
    {
        $this->service = $service;
        $this->method = $method;
    }

    public function __call($method, $arguments)
    {
        if (!method_exists($this->service, $this->method)) {
            throw new \Exception("Method {$this->method} does not exist on class " . get_class($this->service));
        }

        return call_user_func_array([$this->service, $this->method], $arguments);
    }
}