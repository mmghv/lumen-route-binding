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
     * Callback to get routes data allow lazy load.
     *
     * @var callable
     */
    protected $routesResolver;

    /**
     * The binding resolver to handle route-model-binding.
     *
     * @var BindingResolver
     */
    protected $bindingResolver;

    /**
     * Set the routes resolver callable to get routes data.
     *
     * @param callable|null $routesResolver
     */
    public function setRoutesResolver(callable $routesResolver = null)
    {
        $this->routesResolver = $routesResolver;
    }

    /**
     * Set the binding Resolver used to handle route-model-binding.
     *
     * @param BindingResolver|null $bindingResolver
     */
    public function setBindingResolver(BindingResolver $bindingResolver = null)
    {
        $this->bindingResolver = $bindingResolver;
    }

    /**
     * Dispatch the request after getting the routes data if not set at the instantiation.
     *
     * @param  string $httpMethod
     * @param  atring $uri
     *
     * @return integer
     */
    public function dispatch($httpMethod, $uri)
    {
        // If routes resolver callback is set, call it to get the routes data
        if ($this->routesResolver) {
            list($this->staticRouteMap, $this->variableRouteData) = call_user_func($this->routesResolver);
        }

        // Pass the call to the parent fast-route dispatcher
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
