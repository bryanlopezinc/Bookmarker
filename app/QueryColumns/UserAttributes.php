<?php

declare(strict_types=1);

namespace App\QueryColumns;

final class UserAttributes extends Attributes
{
    public static function new(): self
    {
        return new self();
    }

    /**
     * @param string $attributes A comma seperated list of attributes which can only be
     * any of id,username,email,bookmarks_count,password,hasVerifiedEmail
     */
    public static function only(string $attributes): self
    {
        $values = (static::new()->mapAttributes($attributes, [
            'id' => 'id',
            'username' => 'username',
            'email' => 'email',
            'bookmarksCount' => 'bookmarks_count',
            'password' => 'password',
            'hasVerifiedEmail' => 'email_verified_at'
        ]));

        return new static($values);
    }
}
