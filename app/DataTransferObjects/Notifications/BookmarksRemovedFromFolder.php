<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Notifications;

use App\Models\Folder;
use App\Models\User;
use App\Models\Bookmark;

final class BookmarksRemovedFromFolder
{
    /**
     * @param array<Bookmark> $bookmarks
     */
    public function __construct(
        public readonly ?Folder $folder,
        public readonly ?User $collaborator,
        public readonly array $bookmarks,
        public readonly string $uuid,
        public readonly string $notifiedOn
    ) {
    }
}
