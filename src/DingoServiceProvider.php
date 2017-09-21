<?php
namespace mmghv\LumenRouteBinding;

use Dingo\Api\Provider\LumenServiceProvider as BaseDingoServiceProvider;

/**
 * Extending 'dingo/api' service provider to integrate it with lumen-route-binding.
 */
class DingoServiceProvider extends BaseDingoServiceProvider
{
    /**
     * Get lumen-route-binding dispatcher resolver for dingo/api.
     *
     * @return \Closure
     */
    protected function getDispatcherResolver()
    {
        return function ($routeCollector) {
            // Get lumen-route-binding dispatcher from the container.
            $dispatcher = $this->app['dispatcher'];

            // Set routes resolver from dingo router.
            $dispatcher->setRoutesResolver(function() use ($routeCollector) {
                return $routeCollector->getData();
            });
            
            return $dispatcher;
        };
    }
}