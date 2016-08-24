<?php

namespace Itkg\CombinedHttpCache\HttpCache;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Itkg\CombinedHttpCache\Client\RedisClient as CacheClient;

class Store extends BaseStore
{
    protected $cacheConnectionDsn;
    protected $cacheClient;

    protected $localStore = array();

    /**
     * @param string $cacheConnectionDsn    Normalized connection params for the cache client
     * @param string $root                  The path to the cache directory
     * @param array $tagsToExclude          Removes the specified members from the set value stored at key
     */
    public function __construct($cacheConnectionDsn, $root, array $tagsToExclude = array())
    {
        $this->cacheConnectionDsn = $cacheConnectionDsn;

        parent::__construct($root, $tagsToExclude);
    }

    /**
     * @inheritdoc
     */
    protected function load($key)
    {
        // the metadata are only stored and retrieved in Redis, the only coherent place we have
        if ("md" === substr($key, 0, 2)) {
            return $this->getCacheClient()->get($key);
        }

        // the localStore is useful for content digest only
        if (array_key_exists($key, $this->localStore)) {
            return $this->localStore[$key];
        }

        if (false === $res = parent::load($key)) {
            if (false !== $res = $this->getCacheClient()->get($key)) {
                //@todo check if locking needed
                parent::save($key, $res);

                // the local store helps to keep the content ready because lookup in Redis makes follow a GET in the chosen strategy
                $this->localStore[$key] = $res;
            }
        }

        return $res;
    }

    /**
     * @inheritdoc
     */
    protected function save($key, $data)
    {
        //@todo check if locking needed
        $this->getCacheClient()->set($key, $data);

        // store content digest explicitly locally
        if ("md" !== substr($key, 0, 2)) {
            return parent::save($key, $data);
        }
    }

    /**
     * @inheritdoc
     */
    public function lookup(Request $request)
    {
        $key = $this->getCacheKey($request);

        if (!$entries = $this->getMetadata($key)) {
            return;
        }

        // find a cached entry that matches the request.
        $match = null;
        foreach ($entries as $entry) {
            if ($this->requestsMatch(isset($entry[1]['vary'][0]) ? implode(', ', $entry[1]['vary']) : '', $request->headers->all(), $entry[0])) {
                $match = $entry;

                break;
            }
        }

        if (null === $match) {
            return;
        }

        list($req, $headers) = $match;

        // @todo : find a way to overload only this condition
        if (false !== $this->load($digest = $headers['x-content-digest'][0])) {
            return $this->restoreResponse($headers, $this->getPath($digest));
        }
    }

    /**
     * @inheritdoc
     */
    public function write(Request $request, Response $response)
    {
        $key = parent::write($request, $response);

        if (false !== $tagsString = $response->headers->get('X-ITKG-Cache-Tags')){
            $this->getCacheClient()->addTagsToKey($key, explode(',', $tagsString));
        }

        return $key;
    }

    /*
     * Returns the current cache client
     *
     * @return CacheClient
     */
    public function getCacheClient()
    {
        if ($this->cacheClient) {
            return $this->cacheClient;
        }

        return $this->cacheClient = new CacheClient($this->cacheConnectionDsn);
    }
}
