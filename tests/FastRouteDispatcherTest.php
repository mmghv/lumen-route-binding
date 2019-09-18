<?php
namespace Tests;

use Mockery as m;
use PHPUnit\Framework\TestCase;
use FastRoute\RouteParser\Std as RouteParser;
use mmghv\LumenRouteBinding\FastRouteDispatcher;
use FastRoute\DataGenerator\GroupCountBased as DataGenerator;

class FastRouteDispatcherTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Setup stub for FastRoute dispatcher class
        class_alias('Tests\GroupCountBasedStub', 'FastRoute\Dispatcher\GroupCountBased');
    }

    public function setUp(): void
    {
        // Setup a global variable used to record the base dispatcher calls
        // (which our class-under-test is extending) to be asserted here
        $GLOBALS['baseDispacherAssertions'] = [];

        // The routes data which will be passed to the dispatcher
        $this->routeData = [];

        // Stub the binding resolver
        $this->binder = m::mock('mmghv\LumenRouteBinding\BindingResolver');

        // Create a new FastRoute dispatcher (our extended one, the class under test)
        $this->dispatcher = new FastRouteDispatcher(null);
        $this->assertBaseDispatcher('construct', null);

        $this->dispatcher->setBindingResolver($this->binder);
    }

    public function tearDown(): void
    {
        m::close();

        // Assert that no unexpected calls found on the base dispatcher (similar to m::close())
        $this->assertBaseDispatcher(null);
    }

    /**
     * Set routes resolver callback on the dispatcher.
     */
    public function setRoutesResolver()
    {
        $this->dispatcher->setRoutesResolver(function () {
            return [null, $this->routeData];
        });
    }

    /**
     * Assert method calls on the base FastRoute dispatcher.
     *
     * @param  string $method
     * @param  ...  $args
     */
    public function assertBaseDispatcher($method = null, $args = null)
    {
        if (is_null($method)) {
            // Assert that no unexpected calls found on the base dispatcher
            $calls = $GLOBALS['baseDispacherAssertions'];
            if ($calls !== []) {
                $this->fail("-> Found unexpected call(s) on the base dispatcher : ".@var_export($calls, true));
            }

            return;
        }

        $args = func_get_args();
        $call = array_shift($GLOBALS['baseDispacherAssertions']);

        if ($call !== $args) {
            array_shift($args);
            $argsImplode = '';
            foreach ($args as $arg) {
                $argsImplode .= ($argsImplode ? ', ' : '') . @var_export($arg, true);
            }

            $this->fail("-> Failed asserting that method [$method] is called on the base dispatcher with args ($argsImplode)\n-> The actual call is : ".@var_export($call, true));
        }
    }

    public function testDispatcherWorksAsExpected()
    {
        // Assert no calls on the base dispatcher
        $this->assertBaseDispatcher(null);

        // Set the route data :
        // Note that we intendedly populate it with the routes data here
        // (after we setup the dispatcher) to make sure that the dispatcher
        // can be registered before the 'routes' file is loaded.
        $this->routeData = [
            'routes' => 'myRoutes',
            'vars' => ['myVars']
        ];

        // Set the routes resolver
        $this->setRoutesResolver();

        // Expect the binding resolver to be called with the route variables returned from the base dispatcher
        $this->binder->shouldReceive('resolveBindings')->once()
             ->with($this->routeData['vars'])->andReturn(['myVars resolved']);

        // Call the dispatcher (what the application will do)
        $result = $this->dispatcher->dispatch('httpMethod', 'uri');

        // Assert that our dispatcher passed the 'dispatch' method to the base dispatcher
        $this->assertBaseDispatcher('dispatch', 'httpMethod', 'uri');

        // Assert that the method 'dispatchVariableRoute' called on the base dispatcher with the correct data
        $this->assertBaseDispatcher('dispatchVariableRoute', $this->routeData, 'uri');

        // Assert that our dispatcher replaced the variables with the resolved ones
        $this->assertSame($result, [1 => 'data', 2 => ['myVars resolved']], '-> Expected the dispatcher to replace the variables with the resolved ones!');
    }

    public function testDispatcherCanAcceptRoutesOnConstructWithoutResolver()
    {
        // Assert no calls on the base dispatcher
        $this->assertBaseDispatcher(null);

        $this->routeData = [
            'routes' => 'myRoutes',
            'vars' => ['myVars']
        ];

        // Pass routes data on construct and don't use routes resolver
        $this->dispatcher = new FastRouteDispatcher([null, $this->routeData]);
        $this->assertBaseDispatcher('construct', [null, $this->routeData]);

        $this->dispatcher->setBindingResolver($this->binder);

        // Expect the binding resolver to be called with the route variables returned from the base dispatcher
        $this->binder->shouldReceive('resolveBindings')->once()
             ->with($this->routeData['vars'])->andReturn(['myVars resolved']);

        // Call the dispatcher (what the application will do)
        $result = $this->dispatcher->dispatch('httpMethod', 'uri');

        // Assert that our dispatcher passed the 'dispatch' method to the base dispatcher
        $this->assertBaseDispatcher('dispatch', 'httpMethod', 'uri');

        // Assert that the method 'dispatchVariableRoute' called on the base dispatcher with the correct data
        $this->assertBaseDispatcher('dispatchVariableRoute', $this->routeData, 'uri');

        // Assert that our dispatcher replaced the variables with the resolved ones
        $this->assertSame($result, [1 => 'data', 2 => ['myVars resolved']], '-> Expected the dispatcher to replace the variables with the resolved ones!');
    }

    public function testDispatcherCanChangeRoutesWithResolver()
    {
        // Assert no calls on the base dispatcher
        $this->assertBaseDispatcher(null);

        $this->routeData = [
            'routes' => 'myRoutes',
            'vars' => ['myVars']
        ];

        // Pass routes data on construct
        $this->dispatcher = new FastRouteDispatcher([null, $this->routeData]);
        $this->assertBaseDispatcher('construct', [null, $this->routeData]);

        $this->dispatcher->setBindingResolver($this->binder);

        $this->routeData = [
            'routes' => 'myRoutes2',
            'vars' => ['myVars2']
        ];

        // Set the routes resolver to change the routes
        $this->setRoutesResolver();

        $newRouteData = [
            'routes' => 'myRoutes3',
            'vars' => ['myVars3']
        ];

        // Set a new routes resolver to change the routes again
        $this->dispatcher->setRoutesResolver(function() use ($newRouteData) {
            return [null, $newRouteData];
        });

        // Expect the binding resolver to be called with the last route variables set by the resolver
        $this->binder->shouldReceive('resolveBindings')->once()
             ->with($newRouteData['vars'])->andReturn(['myVars resolved']);

        // Call the dispatcher (what the application will do)
        $result = $this->dispatcher->dispatch('httpMethod', 'uri');

        // Assert that our dispatcher passed the 'dispatch' method to the base dispatcher
        $this->assertBaseDispatcher('dispatch', 'httpMethod', 'uri');

        // Assert that the method 'dispatchVariableRoute' called on the base dispatcher with the correct data
        $this->assertBaseDispatcher('dispatchVariableRoute', $newRouteData, 'uri');

        // Assert that our dispatcher replaced the variables with the resolved ones
        $this->assertSame($result, [1 => 'data', 2 => ['myVars resolved']], '-> Expected the dispatcher to replace the variables with the resolved ones!');
    }

    public function testDispatcherDoesNotCallTheResolverIfNoVarsPresent()
    {
        // Assert no calls on the base dispatcher
        $this->assertBaseDispatcher(null);

        // Set the route data :
        $this->routeData = [
            'routes' => 'myRoutes',
            'vars' => null
        ];

        // Set the routes resolver
        $this->setRoutesResolver();

        // Call the dispatcher (what the application will do)
        $result = $this->dispatcher->dispatch('httpMethod', 'uri');

        // Assert calls on the base dispatcher
        $this->assertBaseDispatcher('dispatch', 'httpMethod', 'uri');
        $this->assertBaseDispatcher('dispatchVariableRoute', $this->routeData, 'uri');

        // Assert that our dispatcher replaced the variables with the resolved ones
        $this->assertSame($result, [1 => 'data'], '-> Expected the dispatcher to don\'t call the binding resolver!');
    }

    public function testDispatcherDoesNotCallTheResolverIfNoResolverSet()
    {
        // Assert no calls on the base dispatcher
        $this->assertBaseDispatcher(null);

        // Set the route data :
        $this->routeData = [
            'routes' => 'myRoutes',
            'vars' => ['myVars']
        ];

        // Set the routes resolver
        $this->setRoutesResolver();

        $this->dispatcher->setBindingResolver(null);

        // Call the dispatcher (what the application will do)
        $result = $this->dispatcher->dispatch('httpMethod', 'uri');

        // Assert calls on the base dispatcher
        $this->assertBaseDispatcher('dispatch', 'httpMethod', 'uri');
        $this->assertBaseDispatcher('dispatchVariableRoute', $this->routeData, 'uri');

        // Assert that our dispatcher replaced the variables with the resolved ones
        $this->assertSame($result, [1 => 'data', 2 => ['myVars']], '-> Expected the dispatcher to don\'t call the binding resolver!');
    }
}

class GroupCountBasedStub
{
    protected $staticRouteMap;
    protected $variableRouteData;

    public function __construct($data)
    {
        // Record the call to the method
        $GLOBALS['baseDispacherAssertions'][] = ['construct', $data];

        list($this->staticRouteMap, $this->variableRouteData) = $data;
    }

    public function dispatch($httpMethod, $uri)
    {
        // Record the call to the method
        $GLOBALS['baseDispacherAssertions'][] = ['dispatch', $httpMethod, $uri];

        // Call the method dispatchVariableRoute
        return $this->dispatchVariableRoute($this->variableRouteData, $uri);
    }

    protected function dispatchVariableRoute($routeData, $uri)
    {
        // Record the call to the method
        $GLOBALS['baseDispacherAssertions'][] = ['dispatchVariableRoute', $routeData, $uri];

        $data = [1 => 'data'];

        if ($routeData['vars']) {
            $data[2] = $routeData['vars'];
        }

        return $data;
    }
}
