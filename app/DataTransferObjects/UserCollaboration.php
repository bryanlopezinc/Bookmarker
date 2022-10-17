<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\FolderPermissions as Permissions;

final class UserCollaboration
{
    public function __construct(public readonly Folder $collaboration, public readonly Permissions $permissions)
    {
    }
}
