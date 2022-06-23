<?php

declare(strict_types=1);

namespace App\QueryColumns;

final class BookmarkAttributes extends Attributes
{
    public static function new(): self
    {
        return new self();
    }

    /**
     * @param string $attributes A comma seperated list of attributes which can only be
     * any of id,site,userId,tags
     */
    public static function only(string $attributes): self
    {
        $values = (static::new()->mapAttributes($attributes, [
            'id' => 'id',
            'site' => 'site',
            'userId' => 'user_id',
            'tags' => 'tags'
        ]));

        return new static($values);
    }
}
