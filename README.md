Juice - DI Container for older PHP version
==========================================

Why another container ?
-----------------------

 This is a DI container compatible with PHP 5.2

Example usage
-------------
    
    /**
    * Configuration
    */

    $container = new JuiceContainer();

    $container['cache_dir'] = '/tmp/cache';
    $container['memcache_host'] = 'localhost';
    $container['memcache_port'] = 11211;

    $container['main_cache'] = JuiceDefinition::create('Memcache')
        ->call('connect', array('@memcache_host', '@memcache_port'));

    $container['slow_cache'] = JuiceDefinition::create('FileCache')
        ->arguments(array('@cache_dir'));

    $container['two_level_cache'] = JuiceDefinition::create('TwoLevelCache')
        ->arguments(array('@main_cache', '@slow_cache'));

    
    /**
    * Usage
    */

    $cache = $container['two_level_cache'];
    
    if ($data = $cache->load('expensive_operation_id')) {
        //cache hit
        return;
    }

    $cache->save('expensive_operation_id', do_expensive_operation(), 60);


    


