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
            'url_canonical_hash',
            'has_duplicates',
            'title'
        ];
    }

    /**
     * @param string $attributes A comma separated list of attributes which can only be
     * any of id,source,user_id,tags,is_dead_link,url,url_canonical_hash,has_duplicates,title
     */
    public static function only(string $attributes): self
    {
        if (empty($attributes)) {
            return new static();
        }

        return new static(explode(',', $attributes));
    }
}
