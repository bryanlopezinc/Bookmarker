<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Models\User;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\PublicId\UserPublicId;

final class RemoveCollaboratorData
{
    public function __construct(
        public readonly UserPublicId $collaboratorId,
        public readonly FolderPublicId $folderId,
        public readonly bool $ban,
        public readonly User $authUser
    ) {
    }
}
