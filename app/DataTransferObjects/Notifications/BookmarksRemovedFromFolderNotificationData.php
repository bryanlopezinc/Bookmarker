<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Notifications;

use App\Models\Folder;
use App\Models\User;
use App\Models\Bookmark;
use App\ValueObjects\FolderName;
use App\ValueObjects\FullName;

final class BookmarksRemovedFromFolderNotificationData
{
    /**
     * @param array<Bookmark> $bookmarks
     */
    public function __construct(
        public readonly ?Folder $folder,
        public readonly ?User $collaborator,
        public readonly FullName $collaboratorFullName,
        public readonly int $collaboratorId,
        public readonly int $folderId,
        public readonly FolderName $folderName,
        public readonly array $bookmarks,
        public readonly string $id,
        public readonly string $notifiedOn
    ) {
    }
}
