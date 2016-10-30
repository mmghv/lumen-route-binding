<?php
namespace mmghv\LumenRouteBinding;

use Illuminate\Support\ServiceProvider;

class RouteBindingServiceProvider extends ServiceProvider
{
    /**
     * The binder instance
     *
     * @var mmghv\LumenRouteBinding\BindingResolver
     */
    protected $binder;

    /**
     * Register the service by registering our custom dispatcher.
     */
    public function register()
    {
        // Register our binding resolver in the service container
        $this->binder = new BindingResolver([$this->app, 'make']);
        $this->app->instance('bindingResolver', $this->binder);

        // Create a new FastRoute dispatcher (our extended one)
        $dispatcher = new FastRouteDispatcher(null);

        $dispatcher->setBindingResolver($this->binder);
        $dispatcher->setDispatcherResolver($this->getDispatcherResolver());

        // Set our dispatcher to be used in the application instead of the default one
        $this->app->setDispatcher($dispatcher);
    }

    /**
     * Get the original FastRoute dispatcher resolver callback
     *
     * @return \Closure
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
