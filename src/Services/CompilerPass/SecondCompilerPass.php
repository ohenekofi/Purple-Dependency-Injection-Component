<?php
namespace Purple\Core\Services\CompilerPass;

use Purple\Core\Services\Container;
use Purple\Core\Services\Interface\CompilerPassInterface;

class SecondCompilerPass implements CompilerPassInterface
{
    public function process(Container $container)
    {
        // Example logic to modify parameters
        $parameters = $container->getParameters();
        $parameters['some_param'] = 'new_value';
        $container->setParameters($parameters);

        // Example logic to modify tags
        foreach ($container->findTaggedServiceIds('logging') as $id => $tags) {
            $definition = $container->getDefinition($id);
            $definition->addMethodCall('addTag', ['logging']);
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
