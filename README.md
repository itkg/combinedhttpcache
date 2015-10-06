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

If everything works correctly, then we can uncomment the code lines that Symfony keeps in comment to activate the cache. 

License
-------

See LICENSE file
