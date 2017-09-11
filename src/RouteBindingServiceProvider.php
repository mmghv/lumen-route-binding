<?php
namespace mmghv\LumenRouteBinding;

use FastRoute\RouteCollector;
use Illuminate\Support\ServiceProvider;
use FastRoute\RouteParser\Std as RouteParser;
use FastRoute\DataGenerator\GroupCountBased as DataGenerator;

class RouteBindingServiceProvider extends ServiceProvider
{
    /**
     * The binder instance
     *
     * @var BindingResolver
     */
    protected $binder;

    /**
     * The dispatcher instance
     *
     * @var FastRouteDispatcher
     */
    protected $dispatcher;

    /**
     * Register the service by registering our custom dispatcher.
     */
    public function register()
    {
        // Register our binding resolver in the service container
        $this->binder = new BindingResolver([$this->app, 'make']);
        $this->app->instance('bindingResolver', $this->binder);

        // Create a new FastRoute dispatcher (our extended one)
        // with no routes data at the moment because routes file is not loaded yet
        $this->dispatcher = new FastRouteDispatcher(null);

        // Set binding resolver
        $this->dispatcher->setBindingResolver($this->binder);

        // Set routes resolver (will be called when request is dispatched and the routes file is loaded)
        $this->dispatcher->setRoutesResolver($this->getRoutesResolver());

        // Set our dispatcher to be used by the application instead of the default one
        $this->app->setDispatcher($this->dispatcher);

        // Save the dispatcher in the container in case someone needs it later (you're welcome)
        $this->app->instance('dispatcher', $this->dispatcher);
    }

    /**
     * Get route resolver used to get routes data when the request is dispatched.
     *
     * @return \Closure
     */
    protected function getRoutesResolver()
    {
        return function () {
            // Create fast-route collector
            $routeCollector = new RouteCollector(new RouteParser, new DataGenerator);

            // Get routes data from application
            foreach ($this->getRoutes() as $route) {
                $routeCollector->addRoute($route['method'], $route['uri'], $route['action']);
            }

            return $routeCollector->getData();
        };
    }

    /**
     * Get routes data.
     *
     * @return array
     */
    protected function getRoutes()
    {
        // Support lumen < 5.5 by checking for the router property.
        $router = property_exists($this->app, 'router') ? $this->app->router : $this->app;

        return $router->getRoutes();
    }
}
