<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Notifications;

use App\Models\Folder;
use App\Models\User;
use App\ValueObjects\FolderName;
use App\ValueObjects\FullName;

final class CollaboratorExitNotificationData
{
    public function __construct(
        public readonly ?User $collaborator,
        public readonly ?Folder $folder,
        public readonly int $folderId,
        public readonly int $collaboratorId,
        public readonly FolderName $folderName,
        public readonly FullName $collaboratorFullName,
        public readonly string $uuid,
        public readonly string $notifiedOn
    ) {
    }
}
