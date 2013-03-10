Juice - Fast DI Container for PHP >= 5.2
==========================================

Features
--------

 - small and fast
 - compatible with PHP 5.2
 - lighter than most other PHP DI container implementations (Symfony2, ZF2)
 - fluent service definition interface
 - tag service definitions
 - circular dependency resolver
 - ArrayAccess implementation so it can be easily be mocked in tests with an array

Installing
----------

The container is comprised of a single file, **src/Container.php**

For PHP 5.2 you can use curl / wget / git to download that file and simple require it

For PHP 5.3 on, you can use composer:

    {
        "catacgc/juice-di-container": "dev-master"
    }

After cloning the repo, you can run the tests with phpunit:

    phpunit -c phpunit.xml.dist

Creating the container
-----------------------

    require 'src/Container.php';
    $container = new JuiceContainer();

Container values
----------------

The container stores parameters as key => value, but it's main benefit is the runtime wiring of services

The services are created using two special container values:

- **JuiceDefinition** instances
- valid php **callbacks** (including closures, from PHP 5.3 on) that receive the container instance 
    as their only parameter to allow referencing of other parameters or services

Parameters
----------

    $container['mysql_host'] = 'localhost';
    $container['mysql_user'] = 'username';
    $container['mysql_pass'] = 'password';
    $container['mysql_port'] = 3306;
    
    echo $container['mysql_port'];
    
Service definitions
------------------

Because creating factory methods for every service is tedious, the container specially handles a **JuiceDefinition** 
type that is handy when creating complex services

You can reference other services or parameters in a definition using **@service_id** notation in all of JuiceDefinition
method arguments

### API
    
    // simple class
    $container['conn'] = new JuiceDefinition('Connection');   // => new Connection()
    
    // or using the fluent interface to do the same
    $container['conn'] = JuiceDefinition::create('Connection')
    
    // replacing the class name
    $container['conn'] = JuiceDefinition::create('Connection')->className('ChangedMyMindConnection')
    
    // providing arguments
    JuiceDefinition::create('Connection', array('username', 'password'))
    
    // replacing arguments
    JuiceDefinition::create('Connection')->arguments(array('username1', 'password1'))
    
    // replacing specific argument
    JuiceDefinition::create('Connection')->argument(0, 'new_username')
    
    // calling methods
    JuiceDefinition::create('Connection', array('user', 'pass'))
        ->call('setDb', array('db_name'))
        
### Constructor injection:

    $container['conn'] = new JuiceDefinition('MysqlConnection', array('@mysql_user', '@mysql_password'));
    $container['dbal'] = new JuiceDefinition('Dbal', array('@conn'));
    
Now calling `$dbal = $container['dbal']` is equivalent with calling
    
    $connection = new MysqlConnection('username', 'password');
    $dbal = new Dbal($connection);
    
### Setter injection
    
    $container['conn'] = new JuiceDefinition('Connection');
    $container['dbal'] = JuiceDefinition::create('Dbal')->call('setConnection', array('@conn'))
    
### Extending definitions
    
    $container['conn'] = JuiceDefinition::create('MysqlConnection');
    
    // now in another module, in its configuration 
    
    $connDef = $container->raw('conn');
    $connDef->className('MysqlWrapperConnection');

Adding services from callbacks
------------------------------

Every callable that is passed to the container will be called, when retrieved by the client, with the container 
instance as it's only parameter and the return value will represent the actual 
service / parameter for the associated id.

    $container['db'] = array('Factory', 'createDbConnection');
    $pdoObject = $container['db']; //actually calls the factory method

where the factory will look like:

    class Factory
    {
        public static function createDbConnection($container) // <- note the container parameter
        {
            return new PDO(
                sprintf('mysql:host=%s;dbname=%s', $container['mysql_host'], $container['mysql_dbname']),
                $container['mysql_user'],
                $container['mysql_pass']
            );
        }
    }

  * Note: all callbacks receive the container as their only argument

Service aliasing
----------------

    $container['mysql_default_connection'] = JuiceDefinition::create('MysqlConnection', array('@mysql_username', '@mysql_password'));
    $container['connection'] = '@mysql_default_connection';

Now `$container['connection']` and `$container['mysql_default_connection']` will refer to the same service instance

Escaping callbacks and other special types
---------------------------

There are times when your intent is to actually add a callback as a real parameter, not creating a service definition

For this purpose there is a special wrapper type **JuiceParam** that you can use:

    $container['invalid_callback'] = 'strpos';
    echo $container['invalid_callback'];

Gives an error because for php strpos is a callable type and the container will try to call it with strpos($container) to retrieve the supposed service instance

    $container['callback'] = new JuiceParam('strpos');
    echo $container['callback']; //now echoes the expected 'strpos' string
    
Locking behaviour
-----------------

After a parameter or a service is retrieved from the container, you will not be able to add, overwrite 
or extend services anymore. Eg:
    
    $container['param'] = 1;
    echo $container['param'];
    
    $container['param'] = 2; //throws exception
    
This is done on purpose to enforce the configuration before usage and stable behaviour: once you used a service,
you can count that you have the same service throughout the application.

For testing purposes you can overwrite this behaviour by calling `$container->unlock();`

Creating new service instances
------------------------------

The default behaviour is to retrive the same service instance on each call.

If you want to create new service instances on the fly use the build method:

    $conn1 = $container->build($container->raw('conn'));
    $conn2 = $container->build($container->raw('conn'));

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


    


