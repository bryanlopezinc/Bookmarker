<?php

declare(strict_types=1);

namespace App\Http\Handlers\LeaveFolder;

use App\Models\Folder;
use App\Models\User;
use App\Repositories\Folder\CollaboratorPermissionsRepository;

final class RemovePermissions
{
    private readonly User $authUser;
    private readonly CollaboratorPermissionsRepository $permissionsRepository;

    public function __construct(
        User $authUser,
        CollaboratorPermissionsRepository $permissionsRepository = null,
    ) {
        $this->authUser = $authUser;
        $this->permissionsRepository = $permissionsRepository ??= new CollaboratorPermissionsRepository();
    }

    public function __invoke(Folder $folder): void
    {
        $this->permissionsRepository->delete($this->authUser->id, $folder->id);
    }
}
