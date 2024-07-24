<?php
namespace Purple\Core\Services\Interface;

interface EventDispatcherInterface
{
    public function dispatch(string $eventName, $event = null);
    public function addListener(string $eventName, callable $listener, int $priority = 0);
}
