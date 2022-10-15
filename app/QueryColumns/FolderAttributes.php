<?php

declare(strict_types=1);

namespace App\QueryColumns;

final class FolderAttributes extends Attributes
{
    protected function validAttributes(): array
    {
        return [
            'id',
            'user_id',
            'bookmarks_count',
            'is_public',
            'name',
            'description',
            'tags'
        ];
    }

    /**
     * @param string $attributes A comma separated list of attributes which can only be
     * any of id,user_id,bookmarks_count,is_public,name,description,tags
     */
    public static function only(string $attributes): self
    {
        if (empty($attributes)) {
            return new static();
        }

        return new static(explode(',', $attributes));
    }
}
