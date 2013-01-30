<?php namespace Orno\Ioc;

use Closure, ArrayAccess, ReflectionClass;

class Container implements ArrayAccess
{
    /**
     * Items registered with the container
     *
     * @var array
     */
    protected $values = [];

    /**
     * Shared instances
     *
     * @var array
     */
    protected $shared = [];

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
     * @param  string  $alias
     * @param  mixed   $concrete
     * @param  boolean $shared
     * @return void
     */
    public function register($alias, $concrete = null, $shared = false)
    {
        // if $concrete is null we assume the $alias is a class name that
        // needs to be registered
        if (is_null($concrete)) {
            $concrete = $alias;
        }

        // if $concrete is a pre configured object it is automatically shared
        if (is_object($concrete)) {
            $this->shared[$alias] = $concrete;
        }

        // simply store whatever $concrete is in the container and resolve it
        // when it is requested
        $this->values[$alias] = $concrete;
        $this->values[$alias]['shared'] = $shared === true ?: false;
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
            throw new \InvalidArgumentException(
                'Alias "' . $alias .'" is not registered'
            );
        }

        // if the item is currently stored as a shared item we just return it
        if (array_key_exists($alias, $this->shared)) {
            return $this->shared[$alias];
        }

        // if the item is a closure or pre-configured object we just return it
        if ($this->values[$alias] instanceof Closure) {
            // TODO: sharing for closures!!
            return $this->values[$alias]();
        }

        // if we've got this far we need to build the object and resolve it's dependencies
        $object = $this->build($alias, $this->values[$alias]);

        // do we need to save it as a shared item?
        if ($this->values[$alias]['shared'] === true) {
            $this->shared[$alias] = $object;
        }

        // TODO: allow for passing of a fully qualified namespace\class that is not
        // yet registered with the container, configure it and cache it

        return $object;
    }

    /**
     * Takes the $concrete and instantiates it with and dependencies injected
     * into it's constructor
     *
     * @param  string $alias
     * @param  string $concrete
     * @return object
     */
    public function build($alias, $concrete)
    {
        $reflection = new ReflectionClass($concrete);

        // is concrete an instantiable object?
        if (! $reflection->isInstantiable()) {
            throw new \InvalidArgumentException(
                'Unable to instantiate object attached to alias: ' . $alias
            );
        }

        $construct = $reflection->getConstructor();

        // if the $concrete has no constructor we just return the object
        if (is_null($construct)) {
            return new $concrete;
        }

        // use reflection to get the contructors doc block and parameters, these
        // are passed to the dependencies method to work a little magic and resolve
        // all of our dependencies and implementations
        $docBlock = $contruct->getDocComment();
        $params = $construct->getParameters();

        $dependencies = $this->dependencies($params, $docBlock);

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Recursively resolve dependencies, and dependencies of dependencies etc.. etc..
     * Will first check if the parameters type hint is a class and resolve that, if
     * not it will attempt to resolve an implementation from the parameters annotation
     *
     * @param  array  $params
     * @param  string $docBlock
     * @return array
     */
    public function dependencies($params, $docBlock)
    {
        $dependencies = [];

        foreach ($params as $param) {
            $dependency = $param->getClass();

            // TODO: tidy this up, too fucking messy.

            if (! is_null($dependency)) {
                // if the type hint is a class we just resolve it
                $dependencies[] = $this->resolve($dependency->getName());
            } else {
                // if the type hint is not a class, it could be an interface so
                // we have a last ditch attempt to resolve a class from the
                // parameters @param annotation
                $matches = [];

                $results = preg_match_all(
                    '/@param[\t\s]*(?P<type>[^\t\s]*)[\t\s]*\$(?P<name>[^\t\s]*)/sim',
                    $docBlock,
                    $matches
                );

                if ($results > 0) {
                    foreach ($matches['name'] as $key => $val) {
                        if ($val === $param->getName()) {
                            $dependencies[] $this->resolve($matches['type'][$key]);
                            break;
                        }
                    }
                }
            }
        }

        return $dependencies;
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
