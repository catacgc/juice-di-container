<?php

/**
Copyright (c) Catalin Costache

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is furnished
to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
 */

class JuiceContainer implements ArrayAccess
{
    private $values = array();
    private $definitions = array();
    private $aliases = array();
    private $resolving = array();

    /**
     * Parameters can be passed to the constructor
     *
     * @param array $values The parameters or objects.
     */
    function __construct(array $values = array())
    {
        $this->values = $values;
    }

    /**
     * Sets a parameter or a definition
     */
    public function offsetSet($id, $value)
    {
        if ($value instanceof JuiceParam) {
            $this->values[$id] = $value->value;
            return;
        }

        if (is_callable($value) || $value instanceof JuiceDefinition) {
            $this->definitions[$id] = $value;
            return;
        }

        if (is_string($value) && '@' == $value[0]) {
            $this->aliases[$id] = substr($value, 1);
            return;
        }

        $this->values[$id] = $value;
    }

    /**
     * Gets a parameter or an object.
     *
     * @param  string $id The unique identifier for the parameter or definition
     *
     * @return mixed  The value of the parameter or an object
     *
     * @throws InvalidArgumentException if the identifier is not defined
     */
    public function offsetGet($id)
    {
        if (!empty($this->resolving[$id])) {
            $chain = implode(' -> ', array_keys($this->resolving));
            throw new InvalidArgumentException("Circular dependency found: $chain -> $id");
        }

        $this->resolving[$id] = true;
        $return = $this->getServiceOrParameter($id);
        unset($this->resolving[$id]);

        return $return;
    }

    /**
     * Return the service associated with this unique identifier
     *
     * @param string $id The unique identifier
     * @return mixed
     * @throws InvalidArgumentException
     */
    private function getServiceOrParameter($id)
    {
        if (array_key_exists($id, $this->aliases)) {
            return $this->offsetGet($this->aliases[$id]);
        }

        if (array_key_exists($id, $this->values)) {
            return $this->values[$id];
        }

        if (array_key_exists($id, $this->definitions)) {
            return $this->values[$id] = $this->build($this->definitions[$id]);
        }

        throw new InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
    }

    /**
     * Checks if a parameter or an object is set.
     *
     * @param  string $id The unique identifier for the parameter or service
     *
     * @return Boolean
     */
    public function offsetExists($id)
    {
        return isset($this->values[$id]) || isset($this->definitions[$id]) || isset($this->aliases[$id]);
    }

    /**
     * Unsets a parameter or an object.
     *
     * @param  string $id The unique identifier for the parameter or object
     */
    public function offsetUnset($id)
    {
        unset($this->values[$id]);
        unset($this->definitions[$id]);
        unset($this->aliases[$id]);
    }

    /**
     * Gets a parameter or the closure defining an object.
     *
     * @param  string $id The unique identifier for the parameter or object
     *
     * @return mixed  The value of the parameter or the closure defining an object
     *
     * @throws InvalidArgumentException if the identifier is not defined
     */
    public function raw($id)
    {
        if (array_key_exists($id, $this->values)) {
            return $this->values[$id];
        }

        if (array_key_exists($id, $this->definitions)) {
            return $this->definitions[$id];
        }

        if (array_key_exists($id, $this->aliases)) {
            return $this->aliases[$id];
        }

        throw new InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
    }

    /**
     * Build a definition and return the instantiated object
     *
     * @param $definition
     * @return mixed
     */
    public function build($definition)
    {
        if (is_callable($definition)) {
            return call_user_func($definition, $this);
        }

        return $this->buildFromDefinition($definition);
    }

    protected function buildFromDefinition(JuiceDefinition $definition)
    {
        $reflection = new ReflectionClass($this->resolveValue($definition->class));
        $instance = call_user_func_array(array($reflection, 'newInstance'), $this->resolveValue($definition->constructorArguments));

        foreach ($definition->methodCalls as $call) {
            $methodName = $call[0];
            $methodArguments = $this->resolveValue($call[1]);

            call_user_func_array(array($instance, $methodName), $methodArguments);
        }

        return $instance;
    }

    /**
     * Finds and marks references (strings beginning with an @) to other services or parameters
     */
    protected function resolveValue($value)
    {
        if (is_array($value)) {
            foreach ($value as $index => $val) {
                $value[$index] = $this->resolveValue($val);
            }

            return $value;
        }

        if (is_string($value) && '@' == $value[0]) {
            return $this->offsetGet(substr($value, 1));
        }

        return $value;
    }
}

/**
 * Value object that keeps the service definition metadata
 *
 * $def = new JuiceDefinition('Memcache');
 * $def->call('addServer', array('localhost', 11211));
 *
 */
class JuiceDefinition
{
    public $class;
    public $constructorArguments = array();
    public $methodCalls = array();
    public $tags = array();

    public function __construct($className, $arguments = array())
    {
        $this->className($className);
        $this->arguments($arguments);
    }

    public function className($className)
    {
        $this->class = $className;

        return $this;
    }

    public function arguments($args)
    {
        $this->constructorArguments = array_merge($this->constructorArguments, $args);

        return $this;
    }

    public function argument($index, $value)
    {
        $this->constructorArguments[$index] = $value;

        return $this;
    }

    public function call($method, $arguments = array())
    {
        $this->methodCalls[] = array($method, $arguments);

        return $this;
    }

    public function tag($name, $attributes = array())
    {
        $this->tags[$name] = $attributes;

        return $this;
    }

    /**
     * This is just a utility method to compensate for php < 5.4 lack of access to class members upon instantiation
     * Eg: (new JuiceDefinition())->call('increment')
     */
    public static function create($className, $arguments = array())
    {
        $instance = new self($className, $arguments);

        return $instance;
    }
}

/**
 * Useful when you want to add raw parameters that are meaningful to the container internal processing functions
 * like callable names, service references (@service_name) or service definitions
 *
 * Eg:
 *  $container['param'] = new JuiceParam('strpos);
 *
 */
class JuiceParam
{
    public $value;

    public function __construct($value)
    {
        $this->value = $value;
    }
}

/**
 * Useful when you want to create composed parameters
 *
 * Eg:
 *  $container['memcache_host'] = 'localhost';
 *  $container['memcache_port'] = 11211;
 *  $container['memcache_session_path'] = new JuiceConcat('tcp://', '@memcache_host', '@memcache_port');
 */
class JuiceConcat
{
    public $concat;

    public function __construct()
    {
        $this->concat = func_get_args();
    }
}
