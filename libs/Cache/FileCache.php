<?php
namespace Purple\Libs\Cache;
use Purple\Libs\Cache\Interface\CacheInterface;

use DateTime;

class FileCache implements CacheInterface
{
    private $cacheDir;
    private $ttl = [];
    private $tags = [];
    private $missCallback;
    private $maxSize;
    private $evictionPolicy;
    private $hitCount = 0;
    private $missCount = 0;

    public function __construct(string $cacheDir, int $maxSize = 100, string $evictionPolicy = 'LRU')
    {
        $this->cacheDir = $cacheDir;
        $this->maxSize = $maxSize;
        $this->evictionPolicy = $evictionPolicy;
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    private function getPath(string $key): string
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . md5($key);
    }

    public function get(string $key)
    {
        if ($this->has($key)) {
            $this->hitCount++;
            return unserialize(file_get_contents($this->getPath($key)));
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
        if (count(glob($this->cacheDir . '/*')) >= $this->maxSize) {
            $this->evict();
        }

        file_put_contents($this->getPath($key), serialize($value));
        $this->ttl[$key] = (new DateTime())->getTimestamp() + $ttl;

        return true;
    }

    public function delete(string $key): bool
    {
        $path = $this->getPath($key);
        if (file_exists($path)) {
            unlink($path);
            unset($this->ttl[$key]);
            return true;
        }
        return false;
    }

    public function clear(): bool
    {
        array_map('unlink', glob($this->cacheDir . '/*'));
        $this->ttl = [];
        return true;
    }

    // Additional methods
    public function has(string $key): bool
    {
        $path = $this->getPath($key);
        if (file_exists($path) && $this->ttl[$key] > (new DateTime())->getTimestamp()) {
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
            $currentValue = $this->get($key);
            $newValue = $currentValue + $value;
            $this->set($key, $newValue);
            return true;
        }
        return false;
    }

    public function decrement(string $key, int $value = 1): bool
    {
        if ($this->has($key)) {
            $currentValue = $this->get($key);
            $newValue = $currentValue - $value;
            $this->set($key, $newValue);
            return true;
        }
        return false;
    }

    public function serializeCache(): string
    {
        return serialize([$this->cacheDir, $this->ttl]);
    }

    public function deserializeCache(string $data): bool
    {
        list($this->cacheDir, $this->ttl) = unserialize($data);
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
            // Implement LRU eviction for file cache
        } elseif ($this->evictionPolicy === 'LFU') {
            // Implement LFU eviction for file cache
        }
    }
}