<?php

declare(strict_types=1);

namespace App\QueryColumns;

final class FolderAttributes extends Attributes
{
    public static function new(): self
    {
        return new self();
    }

    /**
     * @param string $attributes A comma seperated list of attributes which can only be
     * any of id,userId,storage,privacy,name,description
     */
    public static function only(string $attributes): self
    {
        $values = (static::new()->mapAttributes($attributes, [
            'id' => 'id',
            'userId' => 'user_id',
            'storage' => 'bookmarks_count',
            'privacy' => 'is_public',
            'name' => 'name',
            'description' => 'description'
        ]));

        return new static($values);
    }
}
