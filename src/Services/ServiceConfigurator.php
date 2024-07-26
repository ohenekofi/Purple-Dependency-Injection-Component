<?php 

class ServiceConfigurator
{
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function set($name, $class = null)
    {
        
        $this->currentService = $name;
        $this->currentServiceClass = $class;

        if ($concrete instanceof Closure) {
             $this->container->set($name, $class);
        }

        $this->container->set($name, $class);
        return $this;
    }

    public function autoDiscover($directory){
        $this->container->autoDiscover($directory);
        return $this;
    }

    public function discovery(array $config)
    {
        $discovery = new ServiceDiscovery($this->container);
        $services = $discovery->discoverFromConfig($config);
        foreach ($services as $id => $definition) {
            $this->container->set($id, $definition['class']);
            if (!empty($definition['arguments'])) {
                $this->container->arguments($id, $definition['arguments']);
            }
            if ($definition['autowire']) {
                $this->container->autowire($id);
            }
            if (!empty($definition['tags'])) {
                foreach ($definition['tags'] as $tag) {
                    $this->container->addTag($id, $tag);
                }
            }
        }
        return $this;
    }

    public function annotationDiscovery(array $config){
        return $this->container->annotationDiscovery( $config);
    }

    public function getDefinition($serviceName)
    {
        // Return the service definition or an error if it does not exist
        $this->container->getDefinition($serviceName);
 
    }

    public function alias($alias)
    {
        $this->container->setAlias($alias, $this->currentService);
        $this->container->asGlobal( $this->currentService, true); // Make alias public by default
        return $this;
    }

    public function setAlias($alias, $service = null)
    {
        $serviceToAlias = $service ?? $this->currentService;
        $this->container->setAlias($alias, $serviceToAlias);
        $this->container->asGlobal($this->currentService, true); // Make alias public by default
        return $this;
    }

    public function addTag( $attributes = [])
    {
        $this->container->addTag($this->currentService, $attributes);
        return $this;
    }

    public function addGlobalMiddleware(MiddlewareInterface $middleware): self
    {
        $this->container->addGlobalMiddleware($middleware);
        return $this;
    }

    public function addServiceMiddleware(MiddlewareInterface $middleware): self
    {
        $this->container->addServiceMiddleware($this->currentService, $middleware);
        return $this;
    }

    public function setScope($scope)
    {
        $this->container->scope($this->currentService, $scope);
        return $this;
    }

 
    public function decorate( $innerServiceId = null)
    {
        $this->container->decorate($this->currentService, $this->currentServiceClass, $innerServiceId);
        return $this;
    }


    public function asGlobal(bool $state){
        //as private or public (bool true/false)--defaults to false private
        $this->container->asGlobal($this->currentService, $state);
        return $this;
    }

    public function asShared(bool $state){
        // as singleton or prototype(new instance per req) (bool true/false)--defaults to true singleton
        $this->container->asShared($this->currentService, $state);
        return $this;
    }

    public function lazy($lazy = true)
    {
        $this->container->setLazy($this->currentService, $lazy);
        return $this;
    }

    public function addArgument($argument)
    {   
        $this->container->addArgument($this->currentService, $argument);
        return $this;
    }

    public function arguments(array $arguments)
    {
        $this->container->arguments($this->currentService, $arguments);
        return $this;
    }

    public function parameters($name, $value)
    {
        $this->container->setParameter($name, $value);
        //$this->currentService = $name;
        return $this;
    }

    public function findTaggedServiceIds($tagName)
    {
        //print_r($this->container->tagsByName[$tagName]);
        return $this->container->tagsByName[$tagName];

    }


    public function setCurrentService($service){
        $this->currentService = $service;
    }

    public function addMethodCall($method, $arguments = [])
    {
        $this->container->addMethodCall($this->currentService, $method, $arguments);
        return $this;
    }

    public function factory($factory)
    {
        $this->container->factory($this->currentService, $factory);
        return $this;
    }

    public function implements($interface)
    {
        //print_r($interface);
        $this->container->implements($this->currentService, $interface);
        return $this;
    }

    public function extends($abstract)
    {
        $this->container->extends($this->currentService, $abstract);
        return $this;
    }

    public function autowire()
    {
        $this->container->autowire($this->currentService);
        return $this;
    }

    public function annotwire()
    {
        $this->container->annotwire($this->currentService);
        return $this;
    }
}