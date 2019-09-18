<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use Illuminate\Http\Request;
use Laravel\Lumen\Application;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class IntegratedTest extends TestCase
{
    public function testRouteBindingAndItsServiceProviderWorksAsExpectedWithLumen()
    {
        // Create new Lumen application
        $app = new Application;

        // Register our RouteBinding service provider
        $app->register('mmghv\LumenRouteBinding\RouteBindingServiceProvider');

        // Get the binder instance
        $binder = $app->make('bindingResolver');

        // Register a simple binding
        $binder->bind('wildcard', function ($val) {
            return "{$val} Resolved";
        });

        $router = isset($app->router) ? $app->router : $app;

        // Register a route with a wildcard
        $router->get('/{wildcard}', function ($wildcard) {
            return response($wildcard);
        });

        // Dispatch the request
        $response = $app->handle(Request::create('/myWildcard', 'GET'));

        // Assert the binding is resolved
        $this->assertSame('myWildcard Resolved', $response->getContent(), '-> Response should be the wildcard value after been resolved!');
    }
}
