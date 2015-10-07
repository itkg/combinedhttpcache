<?php

namespace Itkg\CombinedHttpCache\Client;

use Redis;

class RedisClient
{
    protected $connection;

    /**
     * Construct a redis connection
     *
     * @param   $connectionDsn      DSN string for redis connection
     * @throws  \RuntimeException   If connection cannot be established
     */
    public function __construct($connectionDsn)
    {
        $this->connection = new Redis();
        if (false === $this->connection->connect($connectionDsn)){
            throw new \RuntimeException(sprintf("Cannot connect on Redis with %s", $connectionDsn));
        }
    }

    /**
     * Get a key value from the cache
     *
     * @param   string       $key          The key to retrieve data from
     * @return  bool|string  If the key is found, returns the data back. Returns false otherwise.
     */
    public function get($key)
    {
        return $this->connection->get($key);
    }

    /**
     * Set a key to the provided value in the cache
     *
     * @param   string       $key
     * @param   string       $value
     * @return  bool         Returns true if successful. Otherwise, returns false.
     */
    public function set($key, $value)
    {
        return $this->connection->set($key, $value);
    }

    /**
     * Adds the given key to the tags lists managed on the Redis side.
     *
     * @param   string    $key
     * @param   array     $tags
     * @return  int       Returns the number of times the key has been added to lists. Beware a key can already be in the lists. A successful tagging operation is not when returned value equals count($tags).
     */
    public function addTagsToKey($key, array $tags)
    {
        $nbAddedElements = 0;
        foreach($tags as $tag) {
            $nbAddedElements += $this->connection->sAdd(trim($tag), $key);
        }

        return $nbAddedElements;
    }
}