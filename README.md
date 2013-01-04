Juice - DI Container for older PHP version
==========================================

Why another container ?
-----------------------

 - Because it is compatible with PHP 5.2
 - Because it is lighter than most other container implementations (Symfony2, ZF2)

Creating the container
-----------------------

    require 'di/src/Container.php';
    $container = new JuiceContainer();

Adding parameters
-----------------

    $container['mysql_host'] = 'localhost';
    $container['mysql_user'] = 'username';
    $container['mysql_pass'] = 'password';
    $container['mysql_port'] = 3306;

Adding services from callbacks
------------------------------

    $container['db'] = array('Factory', 'createDbConnection');

where the factory looks like:

    class Factory
    {
        public static function createDbConnection($container)
        {
            return new PDO(
                sprintf('mysql:host=%s;dbname=%s', $container['mysql_host'], $container['mysql_dbname']),
                $container['mysql_user'],
                $container['mysql_pass']
            );
        }
    }

  * Note: all callbacks receive the container as their only argument


Full example usage
------------------
    
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

    $container['cache'] = '@two_level_cache';
    
    /**
    * Usage
    */

    $cache = $container['cache'];
    
    if ($data = $cache->load('expensive_operation_id')) {
        //cache hit
        return;
    }

    $cache->save('expensive_operation_id', do_expensive_operation(), 60);


    


