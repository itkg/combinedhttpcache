README
======

About
-----

This library provides integration of distributed HttpCache based on HttpCache
Symfony component on Redis storage.
- Metadata of request and response stay on Redis
- Cache entries come from Redis and are flushed locally so that fast integration and ESI
   support is kept with local filesystem

Installation
------------

Add the library to your project thanks to composer:
```
composer require itkg/combinedhttpcache
```

Integrate in Symfony
--------------------

Edit app/AppCache.php to change HttpCache base class :
``` php
<?php

require_once __DIR__.'/AppKernel.php';

use Itkg\CombinedHttpCache\HttpCache\HttpCache;

class AppCache extends HttpCache
{
}
```

Then change for testing in dev environment the web/app_dev.php.

**Please note you have to push correct Redis configuration string as a second argument of AppCache constructor.**
 
``` php
...
$loader = require_once __DIR__.'/../app/bootstrap.php.cache';
Debug::enable();

require_once __DIR__.'/../app/AppKernel.php';
require_once __DIR__.'/../app/AppCache.php';

$kernel = new AppKernel('dev', true);
$kernel->loadClassCache();
$kernel = new AppCache($kernel, array('redis_connection' => 'tcp://127.0.0.1:6379'));

// When using the HttpCache, you need to call the method in your front controller instead of relying on the configuration parameter
Request::enableHttpMethodParameterOverride();

$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
```

If everything works correctly, cache can then be activated for 'prod' environment.
For that, just uncomment the code lines that Symfony keeps in comment to activate the cache. 

### Using cache ###

From that point, cache annotations or explicit cache settings for a Response will be managed to make
storage of the current request (or ESI request) in conformity to URL unicity and Vary headers.

See :
- http://symfony.com/doc/current/bundles/SensioFrameworkExtraBundle/annotations/cache.html
- http://symfony.com/doc/current/book/http_cache.html

### Manage tagging for cached requests ###

Any request can be cached (if needed of course) and any cached request can be tagged.
To rely on the same conventions has been the choice to manage tagging. For example, it
will ease the process to move to Varnish even for the projects relying at the moment on this Redis version
of HttpCache.

The library expects meta data tagging in responses by positionning X-ITKG-Cache-Tags.
Tags must be separated thanks to ',' character. Each tag name is trimmed in case space-like characters are present (when splitting the header on comas).

Note : 
- We cannot temporarily use the convention defined in FosHttpCache (https://foshttpcachebundle.readthedocs.org/en/latest/features/tagging.html) .
- The tagging can be followed easily thanks to the web debug toolbar for any "master request" and any ESI request.


### Internal design ###
The cache storage is managed in two places: 
- in Redis for metadata
- in Redis and filesystem for content digest

Note 1 :
The double storage for content digest is quite handy because it keeps the reliability and speed
for ESI management like the standard HttpCache that deals with a PHP include strategy to avoid 
unserialiazation and get benefits of standard opcode cache.

Note 2 : 
Opcode cache needs however correct memory settings because it must mostly avoid evicting cache
entries to be efficient. 

A default configuration value can be done however for starting.
```
memory = size(all php files) + size(all cache/prod/http_cache/)
```

In bash it becomes:
```
du -h --max-depth=0 /var/frontend/cache/prod/http_cache/ /var/www
```

In case (for ITKG it is the case), the best is to measure memory usage thanks to APC.php script
to see how it behaves.



For simple invalidation out of Symfony
-------------------------------------

A specific integration is possible for backend responsible of blocks configuration hence 
the need of invalidation.

``` php
<?php
use Itkg\CombinedHttpCache\Client\RedisClient;

// declare correct autoloading
require_once __DIR__.'/../vendor/autoload.php'; 

// create the client 
$client = new RedisClient('tcp://127.0.0.1:6379');

```

Then the client can be used this way:
``` php
// Here, we remove the keys being both in tag-a and tag-b which means an intersection is computed to make the removal
$res = $client->removeKeysFromTags(array('tag-a', 'tag-b'));

// Here, all the keys present in tag-a are removed and all keys in tag-b too (it makes a union).
$res = $client->removeKeysFromTags(array(array('tag-a'), array('tag-b')));

```

Note : manual key set/get/del should not be performed here because key hashing is a very complex task that only
HttpCache should manage.

License
-------

See LICENSE file
