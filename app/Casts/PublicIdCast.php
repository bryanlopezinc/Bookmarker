<?php

declare(strict_types=1);

namespace App\Casts;

use App\ValueObjects\PublicId\PublicId;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class PublicIdCast implements CastsAttributes
{
    /**
     * @param class-string<PublicId> $class
     */
    public function __construct(private readonly string $class)
    {
    }

    /**
     * Cast the given value.
     *
     * @param array<string, mixed> $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        $class = $this->class;

        return new $class($value);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param array<string, mixed> $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        $class = $this->class;

        return [
            'public_id' => $value instanceof PublicId ? $value->value : (new $class($value))->value
        ];
    }
}
