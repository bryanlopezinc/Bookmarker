<?php

declare(strict_types=1);

namespace App\QueryColumns;

final class BookmarkQueryColumns extends QueryColumns
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
}
