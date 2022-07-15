<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\UpdateBookmarkData;

interface UpdateBookmarkRepositoryInterface
{
    public function update(UpdateBookmarkData $data): Bookmark;
}
