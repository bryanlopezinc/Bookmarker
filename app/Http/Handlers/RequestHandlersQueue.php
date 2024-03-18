<?php

declare(strict_types=1);

namespace App\Http\Handlers;

use App\Contracts\StopsRequestHandling;
use ArrayIterator;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Scope;
use IteratorAggregate;
use Traversable;

/**
 * @template-covariant THandler
 */
final class RequestHandlersQueue implements IteratorAggregate
{
    /**
     * @var array<THandler>
     */
    private readonly array $requestHandlersQueue;

    /**
     * The Application instance.
     */
    private readonly Application $app;

    /**
     * @param array<class-string<THandler>|THandler> $handlers
     */
    public function __construct(array $handlers, Application $app = null)
    {
        $this->app = $app ?: app();
        $this->requestHandlersQueue = $this->getHandlersInstances($handlers);
    }

    /**
     * @return array<THandler>
     */
    private function getHandlersInstances(array $handlers): array
    {
        return array_map(function (string|object $handlerClass) {
            return is_object($handlerClass) ? $handlerClass : $this->app->make($handlerClass);
        }, $handlers);
    }

    /**
     * @return Traversable<THandler>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->requestHandlersQueue);
    }

    /**
     * @param callable(THandler): void $callback
     */
    public function handle(callable $callback): void
    {
        foreach ($this as $handler) {
            $callback($handler);

            if ($handler instanceof StopsRequestHandling && $handler->stopRequestHandling()) {
                break;
            }
        }
    }

    /**
     * Apply the given scope to each handler and execute the given callback on each handler.
     *
     * @param callable(THandler): void  $callback
     */
    public function scope(Builder $builder, callable $callback = null): void
    {
        $callback ??= fn () => null;

        foreach ($this as $handler) {
            $callback($handler);

            if ($handler instanceof Scope) {
                $handler->apply($builder, $builder->getModel());
            }
        }
    }
}
