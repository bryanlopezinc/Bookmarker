<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Notifications;

use App\Models\Folder;
use App\ValueObjects\FolderName;
use App\ValueObjects\PublicId\FolderPublicId;

final class YouHaveBeenKickedOutNotificationData
{
    public function __construct(
        public readonly ?Folder $folder,
        public readonly FolderPublicId $folderId,
        public readonly FolderName $folderName,
        public readonly string $uuid,
        public readonly string $notifiedOn
    ) {
    }
}
