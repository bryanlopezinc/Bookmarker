<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Models\User;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\PublicId\RolePublicId;

final class AddPermissionToRoleData
{
    public function __construct(
        public readonly FolderPublicId $folderId,
        public readonly RolePublicId $roleId,
        public readonly string $permission,
        public readonly User $authUser
    ) {
    }
}
