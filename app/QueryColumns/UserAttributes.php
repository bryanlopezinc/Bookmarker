<?php

declare(strict_types=1);

namespace App\QueryColumns;

final class UserAttributes extends Attributes
{
    public static function new(): self
    {
        return new self();
    }

    protected function validAttributes(): array
    {
        return [
            'id',
            'username',
            'email',
            'bookmarks_count',
            'folders_count',
            'favourites_count',
            'password',
            'email_verified_at',
            'folders_count',
            'email_verified_at',
            'firstname',
            'lastname'
        ];
    }

    /**
     * @param string $attributes A comma separated list of attributes which can only be
     * any of id,username,email,bookmarks_count,password,email_verified_at,
     * folders_count,favourites_count,firstname,lastname
     */
    public static function only(string $attributes): self
    {
        if (empty($attributes)) {
            return new static();
        }

        return new static(explode(',', $attributes));
    }
}
