<?php
namespace Purple\Core\Services\Interface;
use Purple\Core\Services\ContainerConfigurator;

use Purple\Core\Services\Container;

interface BundleInterface
{
    public function build(ContainerConfigurator $containerConfigurator): void;
    public function boot(): void;
}