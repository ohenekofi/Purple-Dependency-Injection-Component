<?php
namespace Purple\Core\Services\CompilerPass;

use Purple\Core\Services\Container;
use Purple\Core\Services\Interface\CompilerPassInterface;

class FirstCompilerPass implements CompilerPassInterface
{
    public function process(Container $container)
    {
        // Example logic to add a new service to the container
        $container->set('new_service', \Purple\Core\Services\NewService::class);
        
        // Example logic to modify an existing service
        if ($container->has('some_service')) {
            $definition = $container->getDefinition('some_service');
            $definition->addMethodCall('setNewDependency', [new \Purple\Core\Services\NewDependency()]);
        }
    }

    public function getPriority(): int
    {
        return 0; // Default priority
    }

    public function getType(): string
    {
        return PassConfig::TYPE_BEFORE_OPTIMIZATION;
    }
}
