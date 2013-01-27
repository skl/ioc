<?php namespace Orno\Ioc;

use Closure, ArrayAccess;

class Container implements ArrayAccess
{
    /**
     * Items registered with the container
     *
     * @var array
     */
    protected $values = [];

    /**
     * @param array $values
     */
    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    /**
     * Register a class name, closure or fully configured item with the container,
     * we will handle dependencies at the time it is requested
     *
     * @param  string $alias
     * @param  mixed  $concrete
     * @return void
     */
    public function register($alias, $concrete = null)
    {
        // if $concrete is null we assume the $alias is a class name that
        // needs to be registered
        if (is_null($concrete)) {
            $concrete = $alias;
        }

        // simply store whatever $concrete is in the container and resolve it
        // when it is requested
        $this->values[$alias] = $concrete;
    }

    /**
     * Resolve and return the requested item
     *
     * @param  string $alias
     * @return mixed
     */
    public function resolve($alias)
    {
        if (! array_key_exists($alias, $this->values)) {
            throw new \InvalidArgumentException('Alias "' . $alias .'" is not registered');
        }

        // if the item is a closure or pre-configured object we just return it
        if ($this->values[$alias] instanceof Closure or is_object($alias)) {
            return is_object($alias) ? $alias : $alias();
        }

        // if we've got this far we need to build the object and resolve it's dependencies
        return $this->build($this->values[$alias]);
    }

    /**
     * Build an object and inject it's dependencies
     *
     * @todo rewrite this with use of reflection and doc block parsing to resolve
     * dependencies if they don't exist in the container
     */
    public function build($concrete)
    {

    }

    /**
     * Gets a value from the container
     *
     * @param  string $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->resolve($key);
    }

    /**
     * Registers a value with the container
     *
     * @param  string $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($key, $value)
    {
        $this->register($key, $value);
    }

    /**
     * Destroys an item in the container
     *
     * @param  string $key
     * @return void
     */
    public function offsetUnset($key)
    {
        unset($this->values[$key]);
    }

    /**
     * Checks if an item is set
     *
     * @param  string $key
     * @return boolean
     */
    public function offsetExists($key)
    {
        return isset($this->values[$key]);
    }
}
