<?php
namespace Purple\Libs\Cache;
use Purple\Libs\Cache\Interface\CacheInterface;

use SplDoublyLinkedList;
use DateTime;

class InMemoryCache implements CacheInterface
{
    private $cache = [];
    private $ttl = [];
    private $tags = [];
    private $missCallback;
    private $maxSize;
    private $evictionPolicy;
    private $cacheList;
    private $hitCount = 0;
    private $missCount = 0;

    public function __construct(int $maxSize = 100, string $evictionPolicy = 'LRU')
    {
        $this->maxSize = $maxSize;
        $this->evictionPolicy = $evictionPolicy;
        $this->cacheList = new SplDoublyLinkedList();
        $this->cacheList->setIteratorMode(SplDoublyLinkedList::IT_MODE_FIFO);
    }

    public function get(string $key)
    {
        if ($this->has($key)) {
            $this->hitCount++;
            if ($this->evictionPolicy === 'LRU') {
                $this->cacheList->push($key);
            }
            $value = $this->cache[$key];
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
        //print_r($value);
        if (count($this->cache) >= $this->maxSize) {
            $this->evict();
        }

        $this->cache[$key] = serialize($value);
        $this->ttl[$key] = (new DateTime())->getTimestamp() + $ttl;
        $this->cacheList->push($key);

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->cache[$key], $this->ttl[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->cache = [];
        $this->ttl = [];
        return true;
    }

    // Additional methods
    public function has(string $key): bool
    {
        if (isset($this->cache[$key]) && $this->ttl[$key] > (new DateTime())->getTimestamp()) {
            return true;
        }

        return false;
    }

    public function getMultiple(array $keys): array
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key);
        }
        return $results;
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
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function getTTL(string $key): ?int
    {
        if ($this->has($key)) {
            return $this->ttl[$key] - (new DateTime())->getTimestamp();
        }
        return null;
    }

    public function updateTTL(string $key, int $ttl): bool
    {
        if ($this->has($key)) {
            $this->ttl[$key] = (new DateTime())->getTimestamp() + $ttl;
            return true;
        }
        return false;
    }

    public function increment(string $key, int $value = 1): bool
    {
        if ($this->has($key)) {
            $this->cache[$key] += $value;
            return true;
        }
        return false;
    }

    public function decrement(string $key, int $value = 1): bool
    {
        if ($this->has($key)) {
            $this->cache[$key] -= $value;
            return true;
        }
        return false;
    }

    public function serializeCache(): string
    {
        return serialize([$this->cache, $this->ttl]);
    }

    public function deserializeCache(string $data): bool
    {
        list($this->cache, $this->ttl) = unserialize($data);
        return true;
    }

    public function addTag(string $key, string $tag): bool
    {
        $this->tags[$tag][] = $key;
        return true;
    }

    public function invalidateTag(string $tag): bool
    {
        if (isset($this->tags[$tag])) {
            foreach ($this->tags[$tag] as $key) {
                $this->delete($key);
            }
            unset($this->tags[$tag]);
            return true;
        }
        return false;
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
            'averageTTL' => array_sum($this->ttl) / count($this->ttl)
        ];
    }

    private function evict(): void
    {
        if ($this->evictionPolicy === 'LRU') {
            $key = $this->cacheList->shift();
            unset($this->cache[$key], $this->ttl[$key]);
        } elseif ($this->evictionPolicy === 'LFU') {
            // Implement LFU eviction
        }
    }
}