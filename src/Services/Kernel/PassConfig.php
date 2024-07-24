<?php 
namespace Purple\Core\Services\Kernel;

class PassConfig
{
    const TYPE_BEFORE_OPTIMIZATION = 'before_optimization';
    const TYPE_OPTIMIZE = 'optimize';
    const TYPE_BEFORE_REMOVING = 'before_removing';
    const TYPE_REMOVE = 'remove';
    const TYPE_AFTER_REMOVING = 'after_removing';
}

/*
PassConfig::TYPE_BEFORE_OPTIMIZATION: Runs before the container is optimized.
PassConfig::TYPE_OPTIMIZE: Runs during the optimization phase.
PassConfig::TYPE_BEFORE_REMOVING: Runs before unused services are removed.
PassConfig::TYPE_REMOVE: Runs while removing unused services.
PassConfig::TYPE_AFTER_REMOVING: Runs after unused services are removed.

Use cases:

TYPE_BEFORE_OPTIMIZATION: Use this if you need to modify service definitions before they're optimized.
TYPE_OPTIMIZE: Use this for optimizations that don't change service definitions.
TYPE_BEFORE_REMOVING: Use this if you need to do something before unused services are removed.
TYPE_REMOVE: Use this if you're implementing custom logic for removing services.
TYPE_AFTER_REMOVING: Use this for operations that should happen after all unused services are gone.



$container->addCompilerPass(new MyCustomCompilerPass(), $pass->getType(), $pass->getPriority());
*/