<?php

namespace Itkg\CombinedHttpCacheBundle\HttpCache;

use Symfony\Bundle\FrameworkBundle\HttpCache\HttpCache as BaseHttpCache;

class HttpCache extends BaseHttpCache
{
    protected function createStore()
    {
        return new Store($this->cacheDir ?: $this->kernel->getCacheDir().'/http_cache');
    }
}
