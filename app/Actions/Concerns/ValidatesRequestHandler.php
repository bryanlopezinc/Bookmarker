<?php

declare(strict_types=1);

namespace App\Actions\Concerns;

trait ValidatesRequestHandler
{
    /**
     * @param array<class-string,object> $handlers
     */
    private function assertHandlersAreUnique(object $handler, array $handlers): void
    {
        if (array_key_exists($key = $handler::class, $handlers)) {
            throw new \Exception("Handler [{$key}] has already been queued.");
        }
    }

    /**
     * @param array<class-string,object> $handlers
     */
    private function assertHandlersQueueIsNotEmpty(array $handlers): void
    {
        if (empty($handlers)) {
            throw new \LogicException("A handler has not been set for the request.");
        }
    }
}
