<?php

namespace Purple\Core\Services\Interface;

use Purple\Core\Services\Container;

interface KernelExtensionInterface
{
    public function load(Container $container): void;
}
