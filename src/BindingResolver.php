<?php
namespace mmghv\LumenRouteBinding;

use InvalidArgumentException;
use Exception;

class BindingResolver
{
    /**
     * The class resolver callable, will be called passing the class
     * name and expected to return an instance of this class
     *
     * @var callable
     */
    protected $classResolver;

    /**
     * Explicit bindings
     * [wildcard_key => [binder, errorHandler]]
     *
     * @var array
     */
    protected $bindings = [];

    /**
     * Namespaces for implicit bindings
     * [namespace, prefix, suffix, method, errorHandler]
     *
     * @var array
     */
    protected $implicitBindings = [];

    /**
     * Composite wildcards bindings
     * [[wildcards], binder, errorHandler]
     *
     * @var array
     */
    protected $compositeBindings = [];

    /**
     * Create new instance
     *
     * @param callable $classResolver
     */
    public function __construct(callable $classResolver)
    {
        $this->classResolver = $classResolver;
    }

    /**
     * Resolve bindings for route parameters
     *
     * @param  array $vars  route parameters
     *
     * @return array        route parameters with bindings resolved
     */
    public function resolveBindings(array $vars)
    {
        // First check if the route $vars as a whole matches any registered composite binding
        if (count($vars) > 1 && !empty($this->compositeBindings)) {
            if ($r = $this->resolveCompositeBinding($vars)) {
                return $r;
            }
        }

        // If no composite binding found, check for explicit and implicit bindings
        if (!empty($this->implicitBindings) || !empty($this->bindings)) {
            foreach ($vars as $var => $value) {
                $vars[$var] = $this->resolveBinding($var, $value);
            }
        }

        return $vars;
    }

    /**
     * Check for and resolve the composite bindings if a match found
     *
     * @param  array $vars  the wildcards array
     *
     * @return array|null   the wildcards array after been resolved, or NULL if no match found
     */
    protected function resolveCompositeBinding($vars)
    {
        $keys = array_keys($vars);

        foreach ($this->compositeBindings as $binding) {
            if ($keys === $binding[0]) {
                $binder = $binding[1];
                $errorHandler = $binding[2];

                $callable = $this->getBindingCallable($binder, null);
                $r = $this->callBindingCallable($callable, $vars, $errorHandler, true);

                if (!is_array($r) || count($r) !== count($vars)) {
                    throw new Exception("Route-Model-Binding (composite-bind) : Return value should be an array and should be of the same count as the wildcards!");
                }

                // Combine the binding results with the keys
                return array_combine($keys, array_values($r));
            }
        }
    }

    /**
     * Resolve binding for the given wildcard
     *
     * @param  string $key    wildcard key
     * @param  string $value  wildcard value
     *
     * @return mixed          resolved binding
     */
    protected function resolveBinding($key, $value)
    {
        // Explicit binding
        if (isset($this->bindings[$key])) {
            list($binder, $errorHandler) = $this->bindings[$key];

            $callable = $this->getBindingCallable($binder, $value);

            return $this->callBindingCallable($callable, $value, $errorHandler);
        }

        // Implicit binding
        foreach ($this->implicitBindings as $binding) {
            $class = $binding['namespace'] . '\\' . $binding['prefix'] . ucfirst($key) . $binding['suffix'];

            if (class_exists($class)) {
                $instance = $this->resolveClass($class);

                // If special method name is defined, use it, otherwise, use the default
                if ($method = $binding['method']) {
                    $callable = [$instance, $method];
                } else {
                    $callable = $this->getDefaultBindingResolver($instance, $value);
                }

                return $this->callBindingCallable(
                    $callable,
                    $value,
                    $binding['errorHandler']
                );
            }
        }

        // Return the value unchanged if no binding found
        return $value;
    }

    /**
     * Get the callable for the binding
     *
     * @param  mixed $binder
     * @param  string $value
     *
     * @return callable
     *
     * @throws Exception
     * @throws InvalidArgumentException
     */
    protected function getBindingCallable($binder, $value)
    {
        // If $binder is a callable then use it, otherwise resolve the callable :
        if (is_callable($binder)) {
            return $binder;

        // If $binder is string (class name or Class@method)
        } elseif (is_string($binder)) {
            // Check if binder is CLass@method callable style
            if (strpos($binder, '@') === false) {
                $class = $binder;
                $method = null;
            } else {
                list($class, $method) = explode('@', $binder);
            }

            if (!class_exists($class)) {
                throw new Exception("Route-Model-Binding : Class not found : [$class]");
            }

            $instance = $this->resolveClass($class);

            // If a custom method defined, use it, Otherwise, use the default binding callable
            if ($method) {
                return [$instance, $method];
            } else {
                return $this->getDefaultBindingResolver($instance, $value);
            }
        }

        throw new InvalidArgumentException('Route-Model-Binding : Invalid binder value, Expected callable or string');
    }

    /**
     * Get the default binding resolver callable
     *
     * @param  string $instance
     * @param  string $value
     *
     * @return \Closure
     */
    protected function getDefaultBindingResolver($instance, $value)
    {
        $instance = $instance->where($instance->getRouteKeyName(), $value);

        // Only the 'firstOrFail' is included in the binding callable to exclude the
        // exceptions of undefined methods (where, getRouteKeyName) from the errorHandler
        return function () use ($instance) {
            return $instance->firstOrFail();
        };
    }

    /**
     * Call the $classResolver callable to get the class instance
     *
     * @param  string $class
     *
     * @return mixed
     */
    protected function resolveClass($class)
    {
        return call_user_func($this->classResolver, $class);
    }

    /**
     * Call the resolved binding callable to get the resolved model(s)
     *
     * @param  callable      $callable      binder callable
     * @param  string|array  $args          wildcard(s) value(s) to be passed to the callable
     * @param  null|callable $errorHandler  handler to be called on exceptions (mostly ModelNotFoundException)
     *
     * @return mixed resolved model(s)
     *
     * @throws Exception
     */
    protected function callBindingCallable($callable, $args, $errorHandler)
    {
        try {
            // Try to call the resolver method and retrieve the model
            if (is_array($args)) {
                return call_user_func_array($callable, $args);
            } else {
                return call_user_func($callable, $args);
            }
        } catch (Exception $e) {
            // If there's an error handler defined, call it, otherwise, re-throw the exception
            if (! is_null($errorHandler)) {
                return call_user_func($errorHandler, $e);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Explicit bind a model (name or closure) to a wildcard key.
     *
     * @param  string           $key           wildcard
     * @param  string|callable  $binder        model name, Class@method or resolver callable
     * @param  null|callable    $errorHandler  handler to be called on exceptions (mostly ModelNotFoundException)
     *
     * @example (simple model binding) :
     * ->bind('user', 'App\User');
     *
     * @example (use custom method (Class@method style))
     * ->bind('user', 'App\User@findUser');
     *
     * @example (custom binding closure)
     * ->bind('article', function($value) {
     *     return \App\Article::where('slug', $value)->firstOrFail();
     * });
     *
     * @example (catch ModelNotFoundException error)
     * ->bind('article', 'App\Article', function($e) {
     *     throw new NotFoundHttpException;    // throw another exception
     *     return new \App\Article();          // or return default value
     * });
     */
    public function bind($key, $binder, callable $errorHandler = null)
    {
        $this->bindings[$key] = [$binder, $errorHandler];
    }

    /**
     * Implicit bind all models in the given namespace
     *
     * @param  string $namespace            the namespace where classes are resolved
     * @param  string $prefix               prefix to be added before class name
     * @param  string $suffix               suffix to be added after class name
     * @param  null|string $method          method name to be called on resolved object, omit it to default to :
     *                                      object->where(object->getRouteKeyname(), $value)->firstOrFail()
     * @param  null|callable $errorHandler  handler to be called on exceptions (mostly ModelNotFoundException)
     *
     * @example (bind all models in 'App' namespace) :
     * ->implicitBind('App');
     *
     * @example (bind all models to their repositories) :
     * ->implicitBind('App\Repositories', '', 'Repository');
     *
     * @example (bind all models to their repositories using custom method) :
     * ->implicitBind('App\Repositories', '', 'Repository', 'findForRoute');
     */
    public function implicitBind($namespace, $prefix = '', $suffix = '', $method = null, callable $errorHandler = null)
    {
        $this->implicitBindings[] = compact('namespace', 'prefix', 'suffix', 'method', 'errorHandler');
    }

    /**
     * Register a composite binding (more than one model) with a specific order
     *
     * @param  array           $keys          wildcards composite
     * @param  string|callable binder         resolver callable or Class@method callable, will be passed the wildcards values
     *                                        and should return an array of resolved values of the same count and order
     * @param  null|callable   $errorHandler  handler to be called on exceptions (which is thrown in the resolver callable)
     *
     * @throws InvalidArgumentException
     *
     * @example (bind 2 wildcards composite {oneToMany relation})
     * ->compositeBind(['post', 'comment'], function($post, $comment) {
     *     $post = \App\Post::findOrFail($post);
     *     $comment = $post->comments()->findOrFail($comment);
     *     return [$post, $comment];
     * });
     *
     * @example (using Class@method callable style)
     * ->compositeBind(['post', 'comment'], 'App\Managers\PostManager@findPostComment');
     */
    public function compositeBind($keys, $binder, callable $errorHandler = null)
    {
        if (!is_array($keys)) {
            throw new InvalidArgumentException('Route-Model-Binding : Invalid $keys value, Expected array of wildcards names');
        }

        if (count($keys) < 2) {
            throw new InvalidArgumentException('Route-Model-Binding : Invalid $keys value, Expected array of more than one wildcard');
        }

        if (is_callable($binder)) {
            // normal callable is acceptable
        } elseif (is_string($binder) && strpos($binder, '@') !== false) {
            // Class@method callable is acceptable
        } else {
            throw new InvalidArgumentException("Route-Model-Binding : Binder must be a callable or a 'Class@method' string");
        }

        $this->compositeBindings[] = [$keys, $binder, $errorHandler];
    }
}
