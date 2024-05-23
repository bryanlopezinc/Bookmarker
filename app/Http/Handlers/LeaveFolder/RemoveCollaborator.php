<?php

declare(strict_types=1);

namespace App\Http\Handlers\LeaveFolder;

use App\Models\Folder;
use App\Models\User;
use App\Repositories\Folder\CollaboratorRepository;

final class RemoveCollaborator
{
    private readonly User $authUser;
    private readonly CollaboratorRepository $collaboratorRepository;

    public function __construct(
        User $authUser,
        CollaboratorRepository $collaboratorRepository = null
    ) {
        $this->authUser = $authUser;
        $this->collaboratorRepository = $collaboratorRepository ??= new CollaboratorRepository();
    }

    public function __invoke(Folder $folder): void
    {
        $this->collaboratorRepository->delete($folder->id, $this->authUser->id);
    }
}
