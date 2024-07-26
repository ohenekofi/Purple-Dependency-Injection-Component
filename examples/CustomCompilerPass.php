<?php

namespace Purple\Core\Services\CompilerPass;

use Purple\Core\Services\Container;
use Purple\Core\Services\Interface\CompilerPassInterface;
use Purple\Core\Services\ContainerConfigurator;
use Purple\Core\Services\Kernel\PassConfig;
use Purple\Core\Services\Reference;

class CustomCompilerPass implements CompilerPassInterface
{
    private $tagName;
    private $methodName;

    public function __construct(string $tagName, string $methodName)
    {
        $this->tagName = $tagName;
        $this->methodName = $methodName;
    }
    /**
     * Modify the container here before it is dumped to PHP code.
     */
    public function process(ContainerConfigurator $containerConfigurator): void
    {
        // Example: Tag all services with a specific interface
        $taggedServices = $containerConfigurator->findTaggedServiceIds('example');

        //print_r( $taggedServices);

        foreach ($taggedServices as $id => $tags) {
            // Set the currentservice
            $containerConfigurator->setCurrentService($tags);
            echo $tags;
            // Add a method call to each tagged service
            $containerConfigurator->addMethodCall('setTester', [new Reference('logger')]);
            // You can also modify other aspects of the service definition here
        }
    }

    /**
     * Get the priority of this compiler pass.
     * 
     * @return int The priority (higher values mean earlier execution)
     */
    public function getPriority(): int
    {
        // This compiler pass will run earlier than default priority passes
        return 10;
    }

    /**
     * Get the type of this compiler pass.
     * 
     * @return string One of the TYPE_* constants in Symfony\Component\DependencyInjection\Compiler\PassConfig
     */
    public function getType(): string
    {
        // This pass runs before optimization, allowing it to modify service definitions
        return PassConfig::TYPE_BEFORE_OPTIMIZATION;
    }
}