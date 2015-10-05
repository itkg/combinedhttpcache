<?php

namespace Itkg\CombinedHttpCacheBundle\HttpCache;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Redis;

class Store extends BaseStore
{
    protected $redisConnection;

    protected $localStore = array();

    /**
     * @inheritdoc
     */
    protected function load($key)
    {
        // the metadata are only stored and retrieved in Redis, the only coherent place we have
        if ("md" === substr($key, 0, 2)) {
            return $this->getRedis()->get($key);
        }

        // the localStore is useful for content digest only
        if (array_key_exists($key, $this->localStore)) {
            return $this->localStore[$key];
        }

        if (false === $res = parent::load($key)) {
            if (false !== $res = $this->getRedis()->get($key)) {
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
        $this->getRedis()->set($key, $data);

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
        if (false !== $body = $this->load($digest = $headers['x-content-digest'][0])) {
            return $this->restoreResponse($headers, $this->getPath($headers['x-content-digest'][0]));
        }
    }

    /*
     * Returns the current Redis connection
     *
     * @return \Redis
     */
    protected function getRedis()
    {
        if ($this->redisConnection) {
            return $this->redisConnection;
        }

        $this->redisConnection = new Redis();
        $this->redisConnection->connect('127.0.0.1', 6379);

        return $this->redisConnection;
    }
}
