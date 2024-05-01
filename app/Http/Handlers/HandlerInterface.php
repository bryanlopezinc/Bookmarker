<?php

declare(strict_types=1);

namespace App\Http\Handlers;

interface HandlerInterface
{
    /**
     * @return array<object|callable>
     */
    public function getHandlers(): array;
}
