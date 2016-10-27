<?php
namespace Tests;

use mmghv\LumenRouteBinding\BindingResolver;
use Mockery as m;

class BindingResolverTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->binder = new BindingResolver(function ($class) {
            return new $class;
        });

        $this->model = m::mock('overload:App\Models\Model');
        $this->myTestRepo = m::mock('overload:App\Repositories\MyTestRepo');

        $this->wildcards = [
            'zero'  => 'val zero',
            'one'   => 'val one',
            'two'   => 'val two',
            'three' => 'val three',
        ];

        $this->expected = $this->wildcards;
    }

    public function tearDown()
    {
        m::close();
    }

    /**
     * setup expectations on mock model to receive :
     * $model->where([$model->getRouteKeyName(), 'wildcard_value'])->firstOrFail();
     *
     * @param  Mock $model  mock model to setup
     */
    public function expectWhereRouteKeyNameFirstOrFail($model, $throwException = false)
    {
        $model->shouldReceive('getRouteKeyName')->once()
              ->andReturn('route_key');

        $query = m::mock()->shouldReceive('firstOrFail')->once()
            ->withNoArgs()
            ->andReturn('bind_result');

        $model->shouldReceive('where')->once()
              ->with(['route_key' => 'wildcard_value'])
              ->andReturn($query->getMock());

        if ($throwException) {
            $query->andThrow(new \Exception);
        }
    }

    public function testNoChangesIfNoVars()
    {
        $r = $this->binder->resolveBindings([]);

        $this->assertSame([], $r);
    }

    public function testNoChangesIfNoBindings()
    {
        $r = $this->binder->resolveBindings($this->wildcards);

        $this->assertSame($this->wildcards, $r);
    }

    public function testNoChangesIfBindingsDontMatch()
    {
        // set bindings
        $this->binder->implicitBind('App\Models');

        $this->binder->bind('model', 'App\Models\Model');

        $this->binder->bind('model2', function () {
            return 1;
        });

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->wildcards, $r);
    }

    public function testExplicitBindCallsWhereRouteKeyNameFirstOrFail()
    {
        $this->expectWhereRouteKeyNameFirstOrFail($this->model);

        $this->wildcards['model'] = 'wildcard_value';
        $this->expected['model'] = 'bind_result';

        // set bindings
        $this->binder->bind('model', 'App\Models\Model');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->expected, $r);
    }

    public function testExplicitBindAcceptsClosure()
    {
        $this->wildcards['model'] = 'wildcard_value';
        $this->expected['model'] = 'bind_result';

        // set bindings
        $this->binder->bind('model', function ($wildcard) {
            return ($wildcard === 'wildcard_value') ? 'bind_result' : 'wrong wildcard value!';
        });

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->expected, $r, '-> Custom closure binding should be called with "wildcard_value" and it\'s return value ("bind_result") should be used as the binding result!');
    }

    /**
     * @expectedException \Exception
     */
    public function testExplicitBindThrowsExceptionIfModelNotFound()
    {
        $this->wildcards['model'] = 'wildcard_value';

        // set bindings
        $this->binder->bind('model', 'App\Models\NotFoundModel');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testExplicitBindThrowsExceptionIfBinderIsInvalid()
    {
        $this->wildcards['model'] = 'wildcard_value';

        // set bindings
        $this->binder->bind('model', ['SomeinvalidBinder']);

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);
    }

    /**
     * @expectedException \Exception
     */
    public function testExplicitBindRethrowsException()
    {
        $this->expectWhereRouteKeyNameFirstOrFail($this->model, true);

        $this->wildcards['model'] = 'wildcard_value';

        // set bindings
        $this->binder->bind('model', 'App\Models\Model');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);
    }

    /**
     * @expectedException \Exception
     */
    public function testExplicitBindWithClosureRethrowsException()
    {
        $this->wildcards['model'] = 'wildcard_value';

        // set bindings
        $this->binder->bind('model', function () {
            throw new \Exception();
        });

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);
    }

    public function testExplicitBindErrorHandler()
    {
        $this->expectWhereRouteKeyNameFirstOrFail($this->model, true);

        $this->wildcards['model'] = 'wildcard_value';
        $this->expected['model'] = 'errorHandler_result';

        // set bindings
        $this->binder->bind('model', 'App\Models\Model', function ($e) {
            if ($e instanceof \Exception) {
                return 'errorHandler_result';
            }
        });

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->expected, $r, '-> Exception should be handled to the errorHandler and "errorHandler_result" should be returned and used as the binding result!');
    }

    public function testExplicitBindWithClosureErrorHandler()
    {
        $this->wildcards['model'] = 'wildcard_value';
        $this->expected['model'] = 'errorHandler_result';

        // set bindings
        $this->binder->bind('model', function () {
            throw new \Exception();
        }, function ($e) {
            if ($e instanceof \Exception) {
                return 'errorHandler_result';
            }
        });

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->expected, $r, '-> Exception thrown in closure should be handled to the errorHandler and "errorHandler_result" should be returned and used as the binding result!');
    }

    public function testImplicitBindCallsWhereRouteKeyNameFirstOrFailByDefault()
    {
        $this->expectWhereRouteKeyNameFirstOrFail($this->model);

        $this->wildcards['model'] = 'wildcard_value';
        $this->expected['model'] = 'bind_result';

        // set bindings
        $this->binder->implicitBind('App\Models');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->expected, $r);
    }

    public function testImplicitBindAcceptsPrefix()
    {
        $this->expectWhereRouteKeyNameFirstOrFail($this->myTestRepo);

        $this->wildcards['testRepo'] = 'wildcard_value';
        $this->expected['testRepo'] = 'bind_result';

        // set bindings
        $this->binder->implicitBind('App\Repositories', 'My');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->expected, $r);
    }

    public function testImplicitBindAcceptsSuffix()
    {
        $this->expectWhereRouteKeyNameFirstOrFail($this->myTestRepo);

        $this->wildcards['myTest'] = 'wildcard_value';
        $this->expected['myTest'] = 'bind_result';

        // set bindings
        $this->binder->implicitBind('App\Repositories', '', 'Repo');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->expected, $r);
    }

    public function testImplicitBindAcceptsPrefixAndSuffix()
    {
        $this->expectWhereRouteKeyNameFirstOrFail($this->myTestRepo);

        $this->wildcards['test'] = 'wildcard_value';
        $this->expected['test'] = 'bind_result';

        // set bindings
        $this->binder->implicitBind('App\Repositories', 'My', 'Repo');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->expected, $r);
    }

    public function testImplicitBindAcceptsDefinedMethodName()
    {
        $this->model->shouldReceive('findForRoute')->once()
             ->with('wildcard_value')->andReturn('bind_result');

        $this->wildcards['model'] = 'wildcard_value';
        $this->expected['model'] = 'bind_result';

        // set bindings
        $this->binder->implicitBind('App\Models', '', '', 'findForRoute');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->expected, $r);
    }

    public function testImplicitBindIgnoresNotFoundModels()
    {
        $this->wildcards['NotFountModel'] = 'wildcard_value';

        // set bindings
        $this->binder->implicitBind('App\Models');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->wildcards, $r, '-> Bindings for classes not found in the given namespace should be ignored and their values should not be touched!');
    }

    /**
     * @expectedException \Exception
     */
    public function testImplicitBindRethrowsException()
    {
        $this->expectWhereRouteKeyNameFirstOrFail($this->model, true);

        $this->wildcards['model'] = 'wildcard_value';

        // set bindings
        $this->binder->implicitBind('App\Models');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);
    }

    /**
     * @expectedException \Exception
     */
    public function testImplicitBindRethrowsExceptionWithDefinedMethod()
    {
        $this->model->shouldReceive('findForRoute')->once()
             ->with('wildcard_value')->andThrow(new \Exception);

        $this->wildcards['model'] = 'wildcard_value';

        // set bindings
        $this->binder->implicitBind('App\Models', '', '', 'findForRoute');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);
    }

    public function testImplicitBindErrorHandler()
    {
        $this->expectWhereRouteKeyNameFirstOrFail($this->model, true);

        $this->wildcards['model'] = 'wildcard_value';
        $this->expected['model'] = 'errorHandler_result';

        // set bindings
        $this->binder->implicitBind('App\Models', '', '', '', function ($e) {
            if ($e instanceof \Exception) {
                return 'errorHandler_result';
            }
        });

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->expected, $r, '-> Exception should be handled to the errorHandler and "errorHandler_result" should be returned and used as the binding result!');
    }

    public function testImplicitBindErrorHandlerWithDefinedMethod()
    {
        $this->model->shouldReceive('findForRoute')->once()
             ->with('wildcard_value')->andThrow(new \Exception);

        $this->wildcards['model'] = 'wildcard_value';
        $this->expected['model'] = 'errorHandler_result';

        // set bindings
        $this->binder->implicitBind('App\Models', '', '', 'findForRoute', function ($e) {
            if ($e instanceof \Exception) {
                return 'errorHandler_result';
            }
        });

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->expected, $r, '-> Exception thrown in the defined method should be handled to the errorHandler and "errorHandler_result" should be returned and used as the binding result!');
    }

    public function testImplicitBindReturnsWithTheFirstMatch()
    {
        $ArticleRepo = m::mock('overload:App\Repositories\Article');
        $ArticleManager = m::mock('overload:App\Managers\Article');

        $this->expectWhereRouteKeyNameFirstOrFail($ArticleRepo);

        $this->wildcards['article'] = 'wildcard_value';
        $this->expected['article'] = 'bind_result';

        // set bindings
        $this->binder->implicitBind('App\NotFound');
        $this->binder->implicitBind('App\Repositories');
        $this->binder->implicitBind('App\Managers');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->expected, $r);
    }

    public function testExplicitBindWorksWithImplicitBind()
    {
        $this->expectWhereRouteKeyNameFirstOrFail($this->model);
        $this->expectWhereRouteKeyNameFirstOrFail($this->myTestRepo);

        $this->wildcards['model'] = 'wildcard_value';
        $this->wildcards['myTestRepo'] = 'wildcard_value';

        $this->expected['model'] = 'bind_result';
        $this->expected['myTestRepo'] = 'bind_result';

        // set bindings
        $this->binder->implicitBind('App\Repositories');
        $this->binder->bind('model', 'App\Models\Model');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->expected, $r);
    }

    public function testExplicitBindTakesPriorityOverImplicitBind()
    {
        $this->expectWhereRouteKeyNameFirstOrFail($this->myTestRepo);

        $this->wildcards['model'] = 'wildcard_value';
        $this->expected['model'] = 'bind_result';

        // set bindings
        $this->binder->implicitBind('App\Models');
        $this->binder->bind('model', 'App\Repositories\MyTestRepo');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->expected, $r, '-> Explicit binding should take Priority over implicit binding');
    }

    public function testMultipleBindingsImplicitAndExplicit()
    {
        $this->wildcards['user'] = 'wildcard_for_user';
        $this->wildcards['article'] = 'wildcard_for_article';
        $this->wildcards['comment'] = 'wildcard_for_comment';
        $this->wildcards['tag'] = 'wildcard_for_tag';
        $this->wildcards['book'] = 'wildcard_value';
        $this->wildcards['car'] = 'wildcard_value';
        
        $this->expected['user'] = 'bind_result_for_user';
        $this->expected['article'] = 'bind_result_for_article';
        $this->expected['comment'] = 'bind_result_for_comment';
        $this->expected['tag'] = 'wildcard_for_tag_result';
        $this->expected['book'] = 'bind_result';
        $this->expected['car'] = 'bind_result';

        // =============================================================
        $user = m::mock('overload:App\Repos\EloquentUserRepo');
        $article = m::mock('overload:App\Repos\EloquentArticleRepo');
        $comment = m::mock('overload:App\Repos\CommentRepo');
        $tag = m::mock('overload:App\Repos\TagRepo');
        $book = m::mock('overload:App\Models\Book');
        $car = m::mock('overload:App\Models\Car');

        $user->shouldReceive('findEloquentForRoute')->once()
            ->with($this->wildcards['user'])->andReturn($this->expected['user']);

        $article->shouldReceive('findEloquentForRoute')->once()
            ->with($this->wildcards['article'])->andReturn($this->expected['article']);

        $comment->shouldReceive('findForRoute')->once()
            ->with($this->wildcards['comment'])->andReturn($this->expected['comment']);

        $this->expectWhereRouteKeyNameFirstOrFail($book);
        $this->expectWhereRouteKeyNameFirstOrFail($car);
        // =============================================================

        // set bindings
        $this->binder->implicitBind('App\Repos', 'Eloquent', 'Repo', 'findEloquentForRoute');
        $this->binder->implicitBind('App\Repos', '', 'Repo', 'findForRoute');
        $this->binder->implicitBind('App\Models');

        $this->binder->bind('tag', function ($val) {
            return $val.'_result';
        });

        $this->binder->bind('book', 'App\Models\Book');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->expected, $r);
    }
}
