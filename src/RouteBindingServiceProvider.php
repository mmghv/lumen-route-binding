<?php
namespace mmghv\LumenRouteBinding;

use Illuminate\Support\ServiceProvider;

class RouteBindingServiceProvider extends ServiceProvider
{
    /**
     * Register RouteModelBinding service by registering out custom dispatcher.
     */
    public function register()
    {
        $app = $this->app;

        // Register our binding resolver in the service container
        $bindingResolver = new BindingResolver([$app, 'make']);
        $app->instance('bindingResolver', $bindingResolver);

        // Create a new FastRoute dispatcher (our extended one)
        $dispatcher = new FastRouteDispatcher(null);

        $dispatcher->setBindingResolver($bindingResolver);
        $dispatcher->setDispatcherResolver($this->getDispatcherResolver());

        // Set our dispatcher to be used in the application instead of the default one
        $app->setDispatcher($dispatcher);
    }

    /**
     * Get the original FastRoute dispatcher resolver callable
     *
     * @return callable
     */
    protected function getDispatcherResolver()
    {
        return function ($dispatcher) {
            return \FastRoute\simpleDispatcher(function ($r) {
                foreach ($this->app->getRoutes() as $route) {
                    $r->addRoute($route['method'], $route['uri'], $route['action']);
                }
            }, [
                'dispatcher' => $dispatcher
            ]);
        };
    }
}
