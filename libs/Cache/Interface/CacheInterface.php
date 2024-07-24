<?php

namespace Purple\Libs\Cache\Interface;

interface CacheInterface
{
    public function get(string $key);
    public function set(string $key, $value, int $ttl = 3600): bool;
    public function delete(string $key): bool;
    public function clear(): bool;

    // New methods
    public function has(string $key): bool;
    public function getMultiple(array $keys): array;
    public function setMultiple(array $items, int $ttl = 3600): bool;
    public function deleteMultiple(array $keys): bool;
    public function getTTL(string $key): ?int;
    public function updateTTL(string $key, int $ttl): bool;
    public function increment(string $key, int $value = 1): bool;
    public function decrement(string $key, int $value = 1): bool;
    public function serializeCache(): string;
    public function deserializeCache(string $data): bool;
    public function addTag(string $key, string $tag): bool;
    public function invalidateTag(string $tag): bool;
    public function registerMissCallback(callable $callback): void;
    public function getMetrics(): array;
}
