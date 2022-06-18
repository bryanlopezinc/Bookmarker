<?php

declare(strict_types=1);

namespace App\QueryColumns;

final class UserAttributes extends Attributes
{
    public static function new(): self
    {
        return new self();
    }

    public function id(): self
    {
        $this->columns[] = 'id';

        return $this;
    }

    public function username(): self
    {
        $this->columns[] = 'username';

        return $this;
    }

    public function email(): self
    {
        $this->columns[] = 'email';

        return $this;
    }

    public function bookmarksCount(): self
    {
        $this->columns[] = 'bookmarks_count';

        return $this;
    }

    public function password(): self
    {
        $this->columns[] = 'password';

        return $this;
    }
}
