<?php

class JuiceContainer implements ArrayAccess
{
    private $values = array();
    private $definitions = array();

    /**
     * Instantiate the container.
     *
     * Objects and parameters can be passed as argument to the constructor.
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
        if (is_callable($value) || $value instanceof JuiceDefinition) {
            $this->definitions[$id] = $value;
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
     * @param  string $id The unique identifier for the parameter or object
     *
     * @return Boolean
     */
    public function offsetExists($id)
    {
        return isset($this->values[$id]) || isset($this->definitions[$id]);
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

        throw new InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
    }

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

interface JuiceServiceProviderInterface
{
    public function register(Juice $container);
}
