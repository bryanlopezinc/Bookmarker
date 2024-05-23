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
     * @param array<class-string<THandler>|THandler> $handlers
     */
    public function __construct(array $handlers, Application $app = null)
    {
        $this->requestHandlersQueue = $this->getHandlersInstances($handlers, $app ?: app());
    }

    /**
     * @return array<THandler>
     */
    private function getHandlersInstances(array $handlers, Application $app): array
    {
        return array_map(function (string|object $handlerClass) use ($app) {
            return is_object($handlerClass) ? $handlerClass : $app->make($handlerClass);
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

            if ($handler instanceof HasHandlersInterface) {
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

            if ($handler instanceof HasHandlersInterface) {
                $this->scopeRecursive($query, $handler->getHandlers());
            }
        }
    }
}
