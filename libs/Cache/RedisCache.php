<?php
namespace Purple\Libs\Cache;
use Purple\Libs\Cache\Interface\CacheInterface;

use Predis\Client;
use DateTime;

class RedisCache implements CacheInterface
{
    private $client;
    private $ttlKeyPrefix = 'ttl_';
    private $tagsKeyPrefix = 'tags_';
    private $missCallback;
    private $maxSize;
    private $evictionPolicy;
    private $hitCount = 0;
    private $missCount = 0;

    public function __construct(Client $client, int $maxSize = 100, string $evictionPolicy = 'LRU')
    {
        $this->client = $client;
        $this->maxSize = $maxSize;
        $this->evictionPolicy = $evictionPolicy;
    }

    public function get(string $key)
    {
        $value = $this->client->get($key);

        if ($value !== null) {
            $this->hitCount++;
            return unserialize($value);
        }

        $this->missCount++;
        if ($this->missCallback) {
            $value = call_user_func($this->missCallback, $key);
            $this->set($key, $value);
            return $value;
        }

        return null;
    }

    public function set(string $key, $value, int $ttl = 3600): bool
    {
        $this->client->set($key, serialize($value));
        $this->client->expire($key, $ttl);

        if ($this->evictionPolicy === 'LRU') {
            // Implement LRU eviction for Redis if needed
        }

        return true;
    }

    public function delete(string $key): bool
    {
        $this->client->del([$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->client->flushdb();
        return true;
    }

    // Additional methods
    public function has(string $key): bool
    {
        return $this->client->exists($key) === 1;
    }

    public function getMultiple(array $keys): array
    {
        $values = $this->client->mget($keys);
        return array_map(function ($value) {
            return $value !== null ? unserialize($value) : null;
        }, $values);
    }

    public function setMultiple(array $items, int $ttl = 3600): bool
    {
        foreach ($items as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(array $keys): bool
    {
        $this->client->del($keys);
        return true;
    }

    public function getTTL(string $key): ?int
    {
        return $this->client->ttl($key);
    }

    public function updateTTL(string $key, int $ttl): bool
    {
        return $this->client->expire($key, $ttl);
    }

    public function increment(string $key, int $value = 1): bool
    {
        $this->client->incrby($key, $value);
        return true;
    }

    public function decrement(string $key, int $value = 1): bool
    {
        $this->client->decrby($key, $value);
        return true;
    }

    public function serializeCache(): string
    {
        $keys = $this->client->keys('*');
        $cacheData = [];
        foreach ($keys as $key) {
            $cacheData[$key] = $this->client->get($key);
        }
        return serialize($cacheData);
    }

    public function deserializeCache(string $data): bool
    {
        $cacheData = unserialize($data);
        foreach ($cacheData as $key => $value) {
            $this->client->set($key, $value);
        }
        return true;
    }

    public function addTag(string $key, string $tag): bool
    {
        $this->client->sadd($this->tagsKeyPrefix . $tag, $key);
        return true;
    }

    public function invalidateTag(string $tag): bool
    {
        $keys = $this->client->smembers($this->tagsKeyPrefix . $tag);
        $this->deleteMultiple($keys);
        $this->client->del([$this->tagsKeyPrefix . $tag]);
        return true;
    }

    public function registerMissCallback(callable $callback): void
    {
        $this->missCallback = $callback;
    }

    public function getMetrics(): array
    {
        return [
            'hitCount' => $this->hitCount,
            'missCount' => $this->missCount,
            'hitRatio' => $this->hitCount / ($this->hitCount + $this->missCount),
            'averageTTL' => $this->calculateAverageTTL()
        ];
    }

    private function calculateAverageTTL(): float
    {
        $keys = $this->client->keys('*');
        $totalTTL = 0;
        $count = 0;

        foreach ($keys as $key) {
            $ttl = $this->client->ttl($key);
            if ($ttl > 0) {
                $totalTTL += $ttl;
                $count++;
            }
        }

        return $count > 0 ? $totalTTL / $count : 0;
    }

    private function evict(): void
    {
        if ($this->evictionPolicy === 'LRU') {
            // Implement LRU eviction for Redis
        } elseif ($this->evictionPolicy === 'LFU') {
            // Implement LFU eviction for Redis
        }
    }
}