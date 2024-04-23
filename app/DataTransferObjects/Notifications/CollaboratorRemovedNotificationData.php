<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Notifications;

use App\Models\Folder;
use App\Models\User;
use App\ValueObjects\FolderName;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\FullName;
use App\ValueObjects\PublicId\UserPublicId;

final class CollaboratorRemovedNotificationData
{
    public function __construct(
        public readonly ?Folder $folder,
        public readonly FolderPublicId $folderId,
        public readonly FolderName $folderName,
        public readonly UserPublicId $collaboratorId,
        public readonly ?User $collaborator,
        public readonly FullName $collaboratorFullName,
        public readonly UserPublicId $removedById,
        public readonly ?User $removedBy,
        public readonly FullName $removedByFullName,
        public readonly string $uuid,
        public readonly string $notifiedOn,
        public readonly bool $wasBanned,
    ) {
    }
}
