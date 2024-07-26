<?php
namespace Purple\Core\Services\Bundles;

use Purple\Core\Services\Interface\BundleInterface;
use Purple\Core\Services\Container;
use Purple\Core\Db\Example\Cacher;
use Purple\Core\Services\ContainerConfigurator;

class ExampleBundle implements BundleInterface
{
    public function build(ContainerConfigurator $containerConfigurator): void
    {
        
        // Register services for this bun  dle
        $containerConfigurator->set('example_cacher', Cacher::class)
            ->addArgument('@migration_manager')
            ->addTag(['example.tag.cacher']);

    }

    public function boot(): void
    {
        // Perform any necessary boot operations
    }
}