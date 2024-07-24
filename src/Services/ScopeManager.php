<?php
namespace Purple\Core\Services;

use ReflectionClass;
use ReflectionMethod;
use Exception;
use Purple\Core\Services\Exception\ServiceAccessException;
use Purple\Core\Services\Exception\ServiceNotFoundException;
use Purple\Core\Services\Exception\DependencyResolutionException;
use Symfony\Component\Yaml\Yaml;
use Purple\Core\Services\Container;
use Purple\Core\Services\ContainerConfigurator;
use Purple\Core\Services\{ConfigurationLoader, ServiceFactory, DependencyResolver};

class ScopeManager
{
    private $container;
    private array $requestScopedServices = [];
    private array $resolvingStack = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
        
    }

    /*
    public function resolveService($id, $asShared)
    {
        switch ($asShared) {
            case true:
                //singleton same instance across all app
                return $this->getSingletonService($id);
            case false:
                // new prototype for not shared. so a new instance each time 
                return $this->container->createService($id);
            default:
                throw $this->container->createService($id);
        }
    }
    */

    public function resolveService($id,bool $directRequest )
    {
        $definition = $this->container->dependencyGraph[$id] ?? null;

        if (!$definition) {
            throw new ServiceNotFoundException("Service '$id' not found");
        }

       
        $asGlobal = $definition['asGlobal'] ?? false;
        $asShared = $definition['asShared'] ?? true;

        // Check if the service is being resolved as a dependency
        $isBeingResolvedAsDependency = !empty($this->resolvingStack);
        

        if (!$asGlobal && $directRequest && !$isBeingResolvedAsDependency) {
            //throw new ServiceAccessException("Service '$id' is not accessible externally");
        }

        $this->resolvingStack[] = $id;

        try {
            if ($asShared ) {
                $service = $this->getSharedService($id, $directRequest);
            } else {
                $service = $this->getPrototypeService($id, $directRequest);
            }
        } finally {
            array_pop($this->resolvingStack);
        }

        return $service;
    }

    private function getSharedService($id, $directRequest)
    {
        if (!isset($this->container->resolvedServices[$id])) {
            $this->container->resolvedServices[$id] = $this->container->createService($id,  $directRequest);
        }
        return $this->container->resolvedServices[$id];
    }

    private function getPrototypeService($id, $directRequest)
    {
        return $this->container->createService($id,  $directRequest);
    }

    public function resetPrototypes()
    {
        $this->prototypes = [];
    }

    public function getSingletonService($id)
    {
        if (!isset($this->container->resolvedServices[$id])) {
            $this->container->resolvedServices[$id] = $this->container->createService($id);
        }
        return $this->container->resolvedServices[$id];
    }

    private function getRequestScopedService($id)
    {
        if (!isset($this->requestScopedServices[$id])) {
            $this->requestScopedServices[$id] = $this->container->createService($id);
        }
        return $this->requestScopedServices[$id];
    }

    private function getProxyService($id)
    {
        // Implementation of proxy service goes here
        // This is a placeholder and should be implemented based on your specific needs
        throw new Exception("Proxy scope not implemented yet");
    }

    public function resetRequestScopedServices()
    {
        $this->requestScopedServices = [];
    }

}
