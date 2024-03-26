<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveCollaborator;

use App\Contracts\FolderRequestHandlerInterface;
use App\Models\Folder;
use App\DataTransferObjects\RemoveCollaboratorData as Data;
use App\Models\BannedCollaborator;
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use App\Repositories\Folder\CollaboratorRepository;

final class RemoveCollaborator implements FolderRequestHandlerInterface
{
    private CollaboratorPermissionsRepository $permissions;
    private CollaboratorRepository $collaboratorRepository;
    private readonly Data $data;

    public function __construct(
        Data $data,
        CollaboratorPermissionsRepository $permissions = null,
        CollaboratorRepository $collaboratorRepository = null
    ) {
        $this->data = $data;
        $this->permissions = $permissions ??= new CollaboratorPermissionsRepository();
        $this->collaboratorRepository = $collaboratorRepository ??= new CollaboratorRepository();
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $this->collaboratorRepository->delete($folder->id, $this->data->collaboratorId);

        $this->permissions->delete($this->data->collaboratorId, $folder->id);

        if ($this->data->ban) {
            BannedCollaborator::query()->create([
                'folder_id' => $folder->id,
                'user_id'   => $this->data->collaboratorId
            ]);
        }
    }
}
