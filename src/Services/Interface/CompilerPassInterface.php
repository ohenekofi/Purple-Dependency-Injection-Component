<?php
namespace Purple\Core\Services\Interface;
use Purple\Core\Services\Container;
use Purple\Core\Services\ContainerConfigurator;

interface CompilerPassInterface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     */
    public function process(ContainerConfigurator $containerConfigurator): void;

    /**
     * Get the priority of this compiler pass.
     * 
     * @return int The priority (higher values mean earlier execution)
     */
    public function getPriority(): int;

    /**
     * Get the type of this compiler pass.
     * 
     * @return string One of the TYPE_* constants in Symfony\Component\DependencyInjection\Compiler\PassConfig
     */
    public function getType(): string;
}
