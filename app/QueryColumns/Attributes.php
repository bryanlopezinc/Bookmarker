<?php

declare(strict_types=1);

namespace App\QueryColumns;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

abstract class Attributes implements Arrayable
{
    protected Collection $columns;

    public function __construct(array $columns = [])
    {
        $this->columns = collect($columns);
    }

    /**
     * @param array<string>|string $field
     */
    public function has(string|array $columns): bool
    {
        foreach ((array) $columns as $column) {
            if (!$this->columns->containsStrict($column)) {
                return false;
            }
        }

        return true;
    }

    public function except(string|array $fields): array
    {
        $fields = (array) $fields;

        return $this->columns->reject(fn (string $field) => in_array($field, $fields, true))->all();
    }

    public function isEmpty(): bool
    {
        return $this->columns->isEmpty();
    }

    public function clear(): static
    {
        $this->columns = collect();

        return $this;
    }

    /**
     * @return array<string>
     */
    public function toArray(): array
    {
        return $this->columns->all();
    }
}
