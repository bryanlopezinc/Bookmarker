<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Models\User;

final class AddPermissionToRoleData
{
    public function __construct(
        public readonly int $folderId,
        public readonly int $roleId,
        public readonly string $permission,
        public readonly User $authUser
    ) {
    }
}
