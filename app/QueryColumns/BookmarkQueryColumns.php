<?php

declare(strict_types=1);

namespace App\QueryColumns;

final class BookmarkQueryColumns
{
    public function __construct(private array $columns = [])
    {
    }

    public static function new(): self
    {
        return new self();
    }

    public function id(): self
    {
        $this->columns[] = 'id';

        return $this;
    }

    public function site(): self
    {
        $this->columns[] = 'site';

        return $this;
    }

    public function userId(): self
    {
        $this->columns[] = 'user_id';

        return $this;
    }

    public function tags(): self
    {
        $this->columns[] = 'tags';

        return $this;
    }


    public function has(string $field): bool
    {
        return in_array($field, $this->columns, true);
    }

    public function except(string|array $fields): array
    {
        $fields = (array) $fields;

        return collect($this->columns)->reject(fn (string $field) => in_array($field, $fields, true))->all();
    }

    public function isEmpty(): bool
    {
        return empty($this->columns);
    }
}
