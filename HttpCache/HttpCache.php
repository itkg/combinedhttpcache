<?php

namespace Itkg\CombinedHttpCache\HttpCache;

use Symfony\Bundle\FrameworkBundle\HttpCache\HttpCache as BaseHttpCache;
use Symfony\Component\HttpKernel\HttpKernelInterface;


class HttpCache extends BaseHttpCache
{
    protected $envOptions;

    public function __construct(HttpKernelInterface $kernel, array $envOptions, $cacheDir = null)
    {
        $this->envOptions = $envOptions;
        parent::__construct($kernel, $cacheDir);
    }

    protected function createStore()
    {
        if (false === $redisConnection = $this->envOptions['redis_connection']) {
            throw new \RuntimeException("Please define redis_connection parameter for the Itkg\\CombinedHttpCache.");
        }
        return new Store($redisConnection, $this->cacheDir ?: $this->kernel->getCacheDir().'/http_cache');
    }
}
