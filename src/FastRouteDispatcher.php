<?php

/*
| =====================================================================
| Extension of the fast-route dispatcher (GroupCountBased class)
| to add support for route-model-binding in Lumen
| =====================================================================
*/

namespace mmghv\LumenRouteBinding;

use Laravel\Lumen\Application;
use FastRoute\Dispatcher\GroupCountBased;

class FastRouteDispatcher extends GroupCountBased
{
    /**
     * The original FastRoute dispatcher resolver callable
     *
     * @var callable
     */
    protected $dispatcherResolver;

    /**
     * The binding resolver to handle route-model-binding
     *
     * @var BindingResolver
     */
    protected $bindingResolver;

    /**
     * Set the original FastRoute dispatcher resolver callable
     *
     * @param callable $dispatcherResolver
     */
    public function setDispatcherResolver(callable $dispatcherResolver)
    {
        $this->dispatcherResolver = $dispatcherResolver;
    }

    /**
     * Set the binding Resolver used to handle route-model-binding
     *
     * @param BindingResolver|null $bindingResolver
     */
    public function setBindingResolver(BindingResolver $bindingResolver = null)
    {
        $this->bindingResolver = $bindingResolver;
    }

    /**
     * The application will call this method to dispatch the routes,
     * we will interrupt it to setup our own dispatcher then we pass
     * the call to the FastRoute dispatcher, this lazy setup allows
     * us to register our service provider before routes file is loaded.
     *
     * @param  string $httpMethod
     * @param  atring $uri
     *
     * @return integer
     */
    public function dispatch($httpMethod, $uri)
    {
        // Create a new FastRoute dispatcher instance
        $dispatcher = call_user_func($this->dispatcherResolver, __CLASS__);

        // Pass the binding resolver
        if ($this->bindingResolver) {
            $dispatcher->setBindingResolver($this->bindingResolver);
        }

        // Pass the call to the fast-route dispatcher
        return $dispatcher->callDispatcher($httpMethod, $uri);
    }

    /**
     * Call the 'dispatch' method on the parent FastRoute dispatcher.
     *
     * @param  string $httpMethod
     * @param  atring $uri
     *
     * @return integer
     */
    protected function callDispatcher($httpMethod, $uri)
    {
        return parent::dispatch($httpMethod, $uri);
    }

    /**
     * Dispatch the route and it's variables, then resolve the route bindings.
     *
     * @param  array  $routeData
     * @param  string $uri
     *
     * @return array
     */
    protected function dispatchVariableRoute($routeData, $uri)
    {
        $routeInfo = parent::dispatchVariableRoute($routeData, $uri);

        if ($this->bindingResolver && isset($routeInfo[2])) {
            $routeInfo[2] = $this->bindingResolver->resolveBindings($routeInfo[2]);
        }

        return $routeInfo;
    }
}
