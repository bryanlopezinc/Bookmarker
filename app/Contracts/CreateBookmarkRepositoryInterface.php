<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DataTransferObjects\Bookmark;

interface CreateBookmarkRepositoryInterface
{
    public function create(Bookmark $bookmark): Bookmark;
}
