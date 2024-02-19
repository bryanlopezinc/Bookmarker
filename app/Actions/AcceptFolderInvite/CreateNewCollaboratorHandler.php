<?php

declare(strict_types=1);

namespace App\Actions\AcceptFolderInvite;

use App\Models\Folder;
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use App\Repositories\Folder\CollaboratorRepository;
use App\UAC;

final class CreateNewCollaboratorHandler implements HandlerInterface, FolderInviteDataAwareInterface
{
    use Concerns\HasInvitationData;

    private readonly CollaboratorRepository $collaboratorRepository;
    private readonly CollaboratorPermissionsRepository $permissions;

    public function __construct(
        CollaboratorRepository $collaboratorRepository = null,
        CollaboratorPermissionsRepository $permissions = null,
    ) {
        $this->collaboratorRepository = $collaboratorRepository ?: new CollaboratorRepository();
        $this->permissions = $permissions ?: new CollaboratorPermissionsRepository();
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $permissions = new UAC($this->invitationData->permissions);

        $this->collaboratorRepository->create(
            $folder->id,
            $this->invitationData->inviteeId,
            $this->invitationData->inviterId
        );

        if ($permissions->isNotEmpty()) {
            $this->permissions->create($this->invitationData->inviteeId, $folder->id, $permissions);
        }
    }
}
