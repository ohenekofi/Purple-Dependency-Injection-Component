<?php
namespace Purple\Core\Services;

use Exception;
use Purple\Core\Services\Exception\ServiceNotFoundException;
use Purple\Core\Services\Exception\DependencyResolutionException;
use Symfony\Component\Yaml\Yaml;
use Purple\Core\Services\Container;
use Purple\Core\Services\ContainerConfigurator;


class ServiceTracking
{
    private $container;
    private $serviceUsageCount = [];
    private $lastUsageTime = [];
    private $gcThreshold = 100; // Number of service calls before running GC
    private $serviceCallCount = 0;
    private $unusedThreshold = 300; // 5 minutes in seconds
  

    public function __construct(Container $container)
    {
        $this->container = $container;
        
    }

    public function trackServiceUsage($id)
    {
        $this->serviceUsageCount[$id] = ($this->serviceUsageCount[$id] ?? 0) + 1;
        $this->lastUsageTime[$id] = time();
        
        $this->serviceCallCount++;
        if ($this->serviceCallCount >= $this->gcThreshold) {
            $this->runGarbageCollection();
        }
    }

    private function runGarbageCollection()
    {
        $currentTime = time();
        foreach ($this->instances as $id => $instance) {
            if (!isset($this->lastUsageTime[$id]) || 
                ($currentTime - $this->lastUsageTime[$id] > $this->unusedThreshold && 
                 ($this->serviceUsageCount[$id] ?? 0) === 0)) {
                $this->removeService($id);
            }
        }
        $this->serviceCallCount = 0;
    }

    private function removeService($id)
    {
        unset($this->instances[$id]);
        unset($this->serviceUsageCount[$id]);
        unset($this->lastUsageTime[$id]);
        // Optionally, call a cleanup method on the service if it exists
        if (method_exists($this->container->instances[$id], 'cleanup')) {
            $this->container->instances[$id]->cleanup();
        }
    }
}
