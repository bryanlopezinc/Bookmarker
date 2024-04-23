<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Notifications;

use App\Models\Folder;
use App\Models\User;
use App\Models\Bookmark;
use App\ValueObjects\FolderName;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\FullName;
use App\ValueObjects\PublicId\UserPublicId;

final class NewFolderBookmarksNotificationData
{
    /**
     * @param array<Bookmark> $bookmarks
     */
    public function __construct(
        public readonly ?Folder $folder,
        public readonly ?User $collaborator,
        public readonly FullName $collaboratorFullName,
        public readonly UserPublicId $collaboratorId,
        public readonly FolderPublicId $folderId,
        public readonly FolderName $folderName,
        public readonly array $bookmarks,
        public readonly string $notificationId,
        public readonly string $notifiedOn
    ) {
    }
}
