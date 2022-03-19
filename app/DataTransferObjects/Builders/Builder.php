<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Builders;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Traits\Conditionable;

abstract class Builder implements Arrayable
{
    use Conditionable;

    /**
     * @param array<mixed> $attributes
     */
    public function __construct(protected array $attributes = [])
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
