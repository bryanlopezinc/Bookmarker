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

    final protected function mapAttributes(string $attributes, array $attributeValueMap): array
    {
        $values = [];

        if (blank($attributes)) {
            return $values;
        }

        foreach (explode(',', $attributes)  as $attribute) {
            if (!array_key_exists($attribute, $attributeValueMap)) {
                throw new \DomainException('unexpected attribute ' . $attribute);
            }

            $values[] = $attributeValueMap[$attribute];
        }

        return $values;
    }

    /**
     * Check if ALL the given columns exists.
     * 
     * @param array<string>|string $field
     */
    final public function has(string|array $columns): bool
    {
        foreach ((array) $columns as $column) {
            if (!$this->columns->containsStrict($column)) {
                return false;
            }
        }

        return true;
    }

    final public function except(string|array $fields): array
    {
        $fields = (array) $fields;

        return $this->columns->reject(fn (string $field) => in_array($field, $fields, true))->all();
    }

    final public function isEmpty(): bool
    {
        return $this->columns->isEmpty();
    }

    /**
     * @return array<string>
     */
    final public function toArray(): array
    {
        return $this->columns->all();
    }
}
