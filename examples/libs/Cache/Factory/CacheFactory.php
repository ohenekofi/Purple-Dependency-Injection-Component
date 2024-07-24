<?php

namespace Purple\Libs\Cache\Factory;
use  Purple\Libs\Cache\RedisCache;
use  Purple\Libs\Cache\FileCache;
use  Purple\Libs\Cache\InMemoryCache;
use Purple\Libs\Cache\Interface\CacheInterface;

use Predis\Client;

class CacheFactory
{
    public static function createCache(string $type, array $config = []): CacheInterface
    {
        switch ($type) {
            case 'redis':
                if (!isset($config['redis'])) {
                    throw new \InvalidArgumentException('Redis configuration is required.');
                }
                return new RedisCache(new Client($config['redis']), $config['maxSize'] ?? 100, $config['evictionPolicy'] ?? 'LRU');
            case 'file':
                if (!isset($config['cacheDir'])) {
                    throw new \InvalidArgumentException('Cache directory is required.');
                }
                return new FileCache($config['cacheDir'], $config['maxSize'] ?? 100, $config['evictionPolicy'] ?? 'LRU');
            case 'memory':
                return new InMemoryCache($config['maxSize'] ?? 100, $config['evictionPolicy'] ?? 'LRU');
            default:
                throw new \InvalidArgumentException('Invalid cache type specified.');
        }
    }
}
