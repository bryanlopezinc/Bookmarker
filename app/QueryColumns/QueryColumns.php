<?php

declare(strict_types=1);

namespace App\QueryColumns;

use Illuminate\Support\Collection;

abstract class QueryColumns
{
    protected Collection $columns;

    public function __construct(array $columns = [])
    {
        $this->columns = collect($columns);
    }

    public function has(string $field): bool
    {
        return $this->columns->containsStrict($field);
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
}
