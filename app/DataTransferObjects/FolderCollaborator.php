<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\FolderPermissions as Permissions;

final class FolderCollaborator
{
    public function __construct(public readonly User $user, public readonly Permissions $permissions)
    {
    }
}
