<?php

declare(strict_types=1);

namespace App\Http\Handlers;

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

    public function handle(mixed $args): void
    {
        $this->callRecursive($args, $this->requestHandlersQueue);
    }

    private function callRecursive(mixed $args, array $handlers): void
    {
        foreach ($handlers as $handler) {
            $handler = is_callable($handler) ? $handler : fn () => null;

            $handler($args);

            if ($handler instanceof HandlerInterface) {
                $this->callRecursive($args, $handler->getHandlers());
            }
        }
    }

    /**
     * Apply the given scope to each handler.
     */
    public function scope(Builder $query): void
    {
        $this->scopeRecursive($query, $this->requestHandlersQueue);
    }

    private function scopeRecursive(Builder $query, array $handlers): void
    {
        foreach ($handlers as $handler) {
            if ($handler instanceof Scope) {
                $handler->apply($query, $query->getModel());
            }

            if ($handler instanceof HandlerInterface) {
                $this->scopeRecursive($query, $handler->getHandlers());
            }
        }
    }
}
