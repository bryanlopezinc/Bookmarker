<?php

declare(strict_types=1);

namespace App\QueryColumns;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

abstract class Attributes implements Arrayable
{
    protected Collection $attributes;

    /**
     * @param array<string> $columns
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = collect($attributes)->each(fn (string $value) => $this->ensureIsValid($value));
    }

    /**
     * The valid fields that can be requested
     *
     * @return array<string>
     */
    abstract protected function validAttributes(): array;

    /**
     * Check if ALL the given attributes exists.
     *
     * @param array<string>|string $attributes
     */
    final public function has(string|array $attributes): bool
    {
        foreach ((array) $attributes as $attribute) {
            $this->ensureIsValid($attribute);
            if (!$this->attributes->containsStrict($attribute)) {
                return false;
            }
        }

        return true;
    }

    private function ensureIsValid(string $name): void
    {
        if (!in_array($name, $this->validAttributes(), true)) {
            throw new \Exception("attribute [$name] has not been registered as a valid attribute");
        }
    }

    final public function except(string|array $attributes): array
    {
        $attributes = (array) $attributes;

        return $this->attributes->reject(function (string $attribute) use ($attributes) {
            $this->ensureIsValid($attribute);

            return in_array($attribute, $attributes, true);
        })->all();
    }

    final public function isEmpty(): bool
    {
        return $this->attributes->isEmpty();
    }

    /**
     * @return array<string>
     */
    final public function toArray(): array
    {
        return $this->attributes->all();
    }
}
