<?php

declare(strict_types=1);

namespace App\QueryColumns;

final class BookmarkAttributes extends Attributes
{
    public static function new(): self
    {
        return new self();
    }

    protected function validAttributes(): array
    {
        return [
            'id',
            'source',
            'user_id',
            'tags',
            'is_dead_link',
            'url',
        ];
    }

    /**
     * @param string $attributes A comma seperated list of attributes which can only be
     * any of id,source,user_id,tags,is_dead_link,url
     */
    public static function only(string $attributes): self
    {
        if (empty($attributes)) {
            return new static();
        }

        return new static(explode(',', $attributes));
    }
}
