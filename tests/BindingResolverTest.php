<?php
namespace Tests;

use Mockery as m;
use PHPUnit\Framework\TestCase;
use mmghv\LumenRouteBinding\BindingResolver;

class BindingResolverTest extends TestCase
{
    public function setUp(): void
    {
        $this->resetBinder();

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

    public function tearDown(): void
    {
        m::close();
    }

    protected function resetBinder()
    {
        $this->binder = new BindingResolver(function ($class) {
            return new $class;
        });
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
              ->with('route_key', 'wildcard_value')
              ->andReturn($query->getMock());

        if ($throwException) {
            $query->andThrow(new \Exception('NotFound'));
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

        $this->binder->compositeBind(['model1', 'model2'], function () {
            return ['model1', 'model2'];
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

    public function testExplicitBindAcceptsClassAtMethodCallableStyle()
    {
        $this->model->shouldReceive('myMethod')->once()
             ->with('wildcard_value')->andReturn('bind_result');

        $this->wildcards['model'] = 'wildcard_value';
        $this->expected['model'] = 'bind_result';

        // set bindings
        $this->binder->bind('model', 'App\Models\Model@myMethod');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->expected, $r, '-> Class@method binding should be called with "wildcard_value" and it\'s return value ("bind_result") should be used as the binding result!');
    }

    public function testExplicitBindAcceptsClosureBinder()
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

    public function testExplicitBindThrowsExceptionIfClassNotFound()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('[App\Models\NotFoundModel]');

        $this->wildcards['model'] = 'wildcard_value';

        // set bindings
        $this->binder->bind('model', 'App\Models\NotFoundModel');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);
    }

    public function testExplicitBindThrowsExceptionIfClassNotFoundUsingClassAtMethodStyle()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('[App\Models\NotFoundModel]');

        $this->wildcards['model'] = 'wildcard_value';

        // set bindings
        $this->binder->bind('model', 'App\Models\NotFoundModel@myMethod');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);
    }

    public function testExplicitBindThrowsExceptionIfMethodNotFoundUsingClassAtMethodStyle()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('NotFoundMethod');

        $this->wildcards['model'] = 'wildcard_value';

        // set bindings
        $this->binder->bind('model', 'App\Models\Model@NotFoundMethod');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);
    }

    public function testExplicitBindThrowsExceptionIfBinderIsInvalid()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid binder value');

        $this->wildcards['model'] = 'wildcard_value';

        // set bindings
        $this->binder->bind('model', ['SomeinvalidBinder']);

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);
    }

    public function testExplicitBindRethrowsException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('NotFound');

        $this->expectWhereRouteKeyNameFirstOrFail($this->model, true);

        $this->wildcards['model'] = 'wildcard_value';

        // set bindings
        $this->binder->bind('model', 'App\Models\Model');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);
    }

    public function testExplicitBindWithClosureRethrowsException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('NotFound');

        $this->wildcards['model'] = 'wildcard_value';

        // set bindings
        $this->binder->bind('model', function () {
            throw new \Exception('NotFound');
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

    public function testImplicitBindRethrowsException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('NotFound');

        $this->expectWhereRouteKeyNameFirstOrFail($this->model, true);

        $this->wildcards['model'] = 'wildcard_value';

        // set bindings
        $this->binder->implicitBind('App\Models');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);
    }

    public function testImplicitBindRethrowsExceptionWithDefinedMethod()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('NotFound');

        $this->model->shouldReceive('findForRoute')->once()
             ->with('wildcard_value')->andThrow(new \Exception('NotFound'));

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
        $this->wildcards['cat'] = 'wildcard_for_cat';
        
        $this->expected['user'] = 'bind_result_for_user';
        $this->expected['article'] = 'bind_result_for_article';
        $this->expected['comment'] = 'bind_result_for_comment';
        $this->expected['tag'] = 'wildcard_for_tag_result';
        $this->expected['book'] = 'bind_result';
        $this->expected['car'] = 'bind_result';
        $this->expected['cat'] = 'bind_result_for_cat';

        // =============================================================
        $user = m::mock('overload:App\Repos\EloquentUserRepo');
        $article = m::mock('overload:App\Repos\EloquentArticleRepo');
        $comment = m::mock('overload:App\Repos\CommentRepo');
        $tag = m::mock('overload:App\Repos\TagRepo');
        $book = m::mock('overload:App\Models\Book');
        $car = m::mock('overload:App\Models\Car');
        $cat = m::mock('overload:App\Models\Cat');

        $user->shouldReceive('findEloquentForRoute')->once()
            ->with($this->wildcards['user'])->andReturn($this->expected['user']);

        $article->shouldReceive('findEloquentForRoute')->once()
            ->with($this->wildcards['article'])->andReturn($this->expected['article']);

        $comment->shouldReceive('findForRoute')->once()
            ->with($this->wildcards['comment'])->andReturn($this->expected['comment']);

        $cat->shouldReceive('findCat')->once()
            ->with($this->wildcards['cat'])->andReturn($this->expected['cat']);

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
        $this->binder->bind('cat', 'App\Models\Cat@findCat');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->expected, $r);
    }

    public function testCompositeBindAcceptsOnlyArrayOfParts()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid $keys value');

        // set bindings
        $this->binder->compositeBind('string', function () {
            //
        });
    }

    public function testCompositeBindAcceptsOnlyArrayOfMoreThanOnePart()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid $keys value');

        // set bindings
        $this->binder->compositeBind(['model'], function () {
            //
        });
    }

    public function testCompositeBindAcceptsArrayOfMoreThanOnePart()
    {
        // set bindings
        $this->binder->compositeBind(['part1', 'part2'], function () {
            //
        });

        $this->binder->compositeBind(['part1', 'part2', 'part3'], function () {
            //
        });

        $this->binder->compositeBind(['part1', 'part2', 'part3', 'part4'], function () {
            //
        });

        $this->assertTrue(true);
    }

    public function testCompositeBindWorks()
    {
        $this->wildcards = ['parent' => 'parent_value', 'child' => 'child_value'];
        $this->expected = ['parent' => 'parent_result', 'child' => 'child_result'];

        // set bindings
        $this->binder->compositeBind(['parent', 'child'], function ($parent, $child) {
            $parent = ($parent === $this->wildcards['parent']) ? $this->expected['parent'] : 'wrong wildcard value for [parent]!';
            $child = ($child === $this->wildcards['child']) ? $this->expected['child'] : 'wrong wildcard value for [child]!';
            return [$parent, $child];
        });

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->expected, $r, '-> Custom closure binding should be called with expected wildcards values and it\'s return should be used as the binding results');
    }

    public function testCompositeBindWorksWithMoreThanTwoWildcards()
    {
        $this->wildcards = ['parent' => 'parent_value', 'child' => 'child_value', 'grand-child' => 'grand-child_value'];
        $this->expected = ['parent' => 'parent_result', 'child' => 'child_result', 'grand-child' => 'grand-child_result'];

        // set bindings
        $this->binder->compositeBind(['parent', 'child', 'grand-child'], function ($parent, $child, $grandChild) {
            $parent = ($parent === $this->wildcards['parent']) ? $this->expected['parent'] : 'wrong wildcard value for [parent]!';
            $child = ($child === $this->wildcards['child']) ? $this->expected['child'] : 'wrong wildcard value for [child]!';
            $grandChild = ($grandChild === $this->wildcards['grand-child']) ? $this->expected['grand-child'] : 'wrong wildcard value for [grand-child]!';
            return [$parent, $child, $grandChild];
        });

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->expected, $r, '-> Custom closure binding should be called with expected wildcards values and it\'s return should be used as the binding results');
    }

    public function testCompositeBindAcceptsClassAtMethodCallableStyle()
    {
        $this->wildcards = ['parent' => 'parent_value', 'child' => 'child_value'];
        $this->expected = ['parent' => 'parent_result', 'child' => 'child_result'];

        $this->model->shouldReceive('myMethod')->once()
             ->with($this->wildcards['parent'], $this->wildcards['child'])
             ->andReturn([$this->expected['parent'], $this->expected['child']]);

        // set bindings
        $this->binder->compositeBind(['parent', 'child'], 'App\Models\Model@myMethod');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->expected, $r, '-> Class@method binding should be called with expected wildcards values and it\'s return should be used as the binding results');
    }

    public function testCompositeBindAcceptsOnlyCallableOrClassAtMethodString()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Binder must be');

        // set bindings
        $this->binder->compositeBind(['parent', 'child'], 'App\Models\Model');
    }

    public function testCompositeBindSquawksIfClassNotFound()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('[App\Models\NotFoundModel]');

        $this->wildcards = ['parent' => 'parent_value', 'child' => 'child_value'];

        // set bindings
        $this->binder->compositeBind(['parent', 'child'], 'App\Models\NotFoundModel@myMethod');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);
    }

    public function testCompositeBindSquawksIfMethodNotFound()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('NotFoundMethod');

        $this->wildcards = ['parent' => 'parent_value', 'child' => 'child_value'];

        // set bindings
        $this->binder->compositeBind(['parent', 'child'], 'App\Models\Model@NotFoundMethod');

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);
    }

    public function testCompositeBindOnlyMatchesTheWholeWildcardsPartsWithTheSameOrder()
    {
        // =========================================================================
        //
        $scenario = 'count does not match "<"';
        $this->resetBinder();
        $this->wildcards = ['wildcard1' => 1, 'wildcard2' => 2];

        // set bindings
        $this->binder->compositeBind(['wildcard1', 'wildcard2', 'wildcard3'], function () {
            return [0, 0, 0];
        });

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->wildcards, $r, "-> composite binding should not match ($scenario) so the wildcards should be returned as is without being touched!");
        // =========================================================================
        //
        $scenario = 'count does not match ">"';
        $this->resetBinder();
        $this->wildcards = ['wildcard1' => 1, 'wildcard2' => 2, 'wildcard3' => 3];

        // set bindings
        $this->binder->compositeBind(['wildcard1', 'wildcard2'], function () {
            return [0, 0];
        });

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->wildcards, $r, "-> composite binding should not match ($scenario) so the wildcards should be returned as is without being touched!");
        // =========================================================================
        //
        $scenario = 'first wildcard does not match';
        $this->resetBinder();
        $this->wildcards = ['wildcard1' => 1, 'wildcard2' => 2, 'wildcard3' => 3];

        // set bindings
        $this->binder->compositeBind(['no-match', 'wildcard2', 'wildcard3'], function () {
            return [0, 0, 0];
        });

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->wildcards, $r, "-> composite binding should not match ($scenario) so the wildcards should be returned as is without being touched!");
        // =========================================================================
        //
        $scenario = 'last wildcard does not match';
        $this->resetBinder();
        $this->wildcards = ['wildcard1' => 1, 'wildcard2' => 2, 'wildcard3' => 3];

        // set bindings
        $this->binder->compositeBind(['wildcard1', 'wildcard2', 'no-match'], function () {
            return [0, 0, 0];
        });

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->wildcards, $r, "-> composite binding should not match ($scenario) so the wildcards should be returned as is without being touched!");
        // =========================================================================
        //
        $scenario = 'composite order does not match';
        $this->resetBinder();
        $this->wildcards = ['wildcard1' => 1, 'wildcard2' => 2];

        // set bindings
        $this->binder->compositeBind(['wildcard2', 'wildcard1'], function () {
            return [0, 0];
        });

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->wildcards, $r, "-> composite binding should not match ($scenario) so the wildcards should be returned as is without being touched!");
        // =========================================================================
    }

    public function testCompositeBindSquawksIfReturnValueIsNotAnArray()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Return value should be');

        $this->wildcards = ['parent' => 'parent_value', 'child' => 'child_value'];
        $this->expected = ['parent' => 'parent_result', 'child' => 'child_result'];

        // set bindings
        $this->binder->compositeBind(['parent', 'child'], function () {
            return 'NoneArrayValue';
        });

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);
    }

    public function testCompositeBindSquawksIfReturnValueIsNotAnArrayOfTheSameCountAsTheWildcards()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Return value should be');

        $this->wildcards = ['parent' => 'parent_value', 'child' => 'child_value'];
        $this->expected = ['parent' => 'parent_result', 'child' => 'child_result'];

        // set bindings
        $this->binder->compositeBind(['parent', 'child'], function () {
            return ['ArrayOfOneItem'];
        });

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);
    }

    public function testCompositeBindRethrowsException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('NotFound');

        $this->wildcards = ['parent' => 'parent_value', 'child' => 'child_value'];
        $this->expected = ['parent' => 'parent_result', 'child' => 'child_result'];

        // set bindings
        $this->binder->compositeBind(['parent', 'child'], function () {
            throw new \Exception('NotFound');
        });

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);
    }

    public function testCompositeBindErrorHandler()
    {
        $this->wildcards = ['parent' => 'parent_value', 'child' => 'child_value'];
        $this->expected = ['parent' => 'errorHandler_result1', 'child' => 'errorHandler_result2'];

        // set bindings
        $this->binder->compositeBind(['parent', 'child'], function () {
            throw new \Exception();
        }, function ($e) {
            if ($e instanceof \Exception) {
                return ['errorHandler_result1', 'errorHandler_result2'];
            }
        });

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->expected, $r, '-> Exception thrown in closure should be handled to the errorHandler and then it\'s return should be used as the binding result!');
    }

    public function testCompositeBindTakesPriorityOverOtherBindings()
    {
        $this->wildcards = ['model' => 'model_value', 'child' => 'child_value'];
        $this->expected = ['model' => 'model_result', 'child' => 'child_result'];

        // set bindings
        $this->binder->implicitBind('App\Models');
        $this->binder->bind('model', 'App\Repositories\MyTestRepo');

        $this->binder->compositeBind(['model', 'child'], function () {
            return ['model_result', 'child_result'];
        });

        // resolve bindings (done when route is dispatched)
        $r = $this->binder->resolveBindings($this->wildcards);

        // assert resolved bindings
        $this->assertSame($this->expected, $r, '-> Composite binding should take Priority over explicit binding and implicit binding');
    }
}
