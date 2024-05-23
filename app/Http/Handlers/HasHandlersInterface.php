<?php

declare(strict_types=1);

namespace App\Http\Handlers;

interface HasHandlersInterface
{
    /**
     * @return array<object|callable>
     */
    public function getHandlers(): array;
}
