<?php
namespace Purple\Core\Services\Interface;

use Closure;

interface MiddlewareInterface
{
    public function process($service, string $id, Closure $next);
}
