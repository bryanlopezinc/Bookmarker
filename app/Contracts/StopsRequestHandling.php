<?php

declare(strict_types=1);

namespace App\Contracts;

interface StopsRequestHandling
{
    public function stopRequestHandling(): bool;
}
