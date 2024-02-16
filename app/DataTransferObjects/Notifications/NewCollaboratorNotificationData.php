<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Notifications;

use App\Models\Folder;
use App\Models\User;
use App\ValueObjects\FolderName;
use App\ValueObjects\FullName;

final class NewCollaboratorNotificationData
{
    public function __construct(
        public readonly ?User $collaborator,
        public readonly ?Folder $folder,
        public readonly ?User $newCollaborator,
        public readonly int $collaboratorId,
        public readonly int $newCollaboratorId,
        public readonly FullName $collaboratorFullName,
        public readonly FullName $newCollaboratorFullName,
        public readonly int $folderId,
        public readonly FolderName $folderName,
        public readonly string $uuid,
        public readonly string $notifiedOn
    ) {
    }
}
