<?php
namespace Purple\Core\Services\Exception;
use  Exception;

class DependencyResolutionException extends Exception
{
    private $dependencyChain;

    public function __construct($message, array $dependencyChain)
    {
        parent::__construct($message);
        $this->dependencyChain = $dependencyChain;
    }

    public function getDependencyChain()
    {
        return $this->dependencyChain;
    }
    
    public static function forUnresolvableDependency($dependency)
    {
        return new self("Unresolvable dependency: $dependency");
    }

    public static function forCircularDependency($serviceName)
    {
        return new self("Circular dependency detected for service: $serviceName");
    }
}