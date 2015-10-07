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

    /**
     * Remove the keys matching the given tags.
     * - By intersection if one dimension array is provided.
     * - If 2-dimensions array is provided
     *   - sUion is applied between 1st dimension keys
     *   - in the 1st dimension key, sInter is applied between the 2nd dimension keys
     *
     * Example :
     *  The given array :   array( array('tag0', 'tag1'), array('tag2', 'tag3'))
     *  It becomes :        sunion( sinter('tag0', 'tag1'), sinter('tag2', 'tag3') )
     *
     * If no multidimension is set to the tags array, keys removal will be managed by intersection.
     * array('tag0', 'tag1') will become array( array('tag0', 'tag1') )
     *
     * @param   array   $tags
     * @return  array   Returns an array ('attempted' => array($keys), 'really_deleted' => (int) )
     */
    public function removeKeysFromTags(array $tags)
    {
        //return $this->connection->sMembers('site-2');
        if (isset($tags[0]) && !is_array($tags[0])) {
            $tags = array($tags);
        }

        $mergedKeys = array();
        foreach($tags as $intersectExpression) {
            $currentKeys = count($intersectExpression) > 1 ?
                $this->executeEval('sinter', $intersectExpression)
                : $this->connection->sMembers($intersectExpression[0]);

            $mergedKeys = is_array($currentKeys) ? array_merge($mergedKeys, $currentKeys) : $mergedKeys;

        }
        // execute key removal on unique keys and return them
        $mergedKeys = array_unique($mergedKeys);
        $nbDeleted = $this->executeEval('del', $mergedKeys);

        return array('attempted' => $mergedKeys, 'really_deleted' => $nbDeleted);
    }

    protected function executeEval($operation, $arguments)
    {
        return $this->connection->eval("return redis.call('$operation', '" . implode("','", $arguments) . "') ");
    }
}
