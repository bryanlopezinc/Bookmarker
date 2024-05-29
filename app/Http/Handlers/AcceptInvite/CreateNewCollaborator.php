<?php

declare(strict_types=1);

namespace App\Http\Handlers\AcceptInvite;

use App\DataTransferObjects\FolderInviteData;
use App\Models\Folder;
use App\Models\FolderCollaboratorRole;
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use App\Repositories\Folder\CollaboratorRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class CreateNewCollaborator implements Scope
{
    private readonly CollaboratorRepository $collaboratorRepository;
    private readonly CollaboratorPermissionsRepository $permissions;
    private readonly FolderInviteData $invitationData;

    public function __construct(
        FolderInviteData $invitationData,
        CollaboratorRepository $collaboratorRepository = null,
        CollaboratorPermissionsRepository $permissions = null,
    ) {
        $this->invitationData = $invitationData;
        $this->collaboratorRepository = $collaboratorRepository ??= new CollaboratorRepository();
        $this->permissions = $permissions ??= new CollaboratorPermissionsRepository();
    }

    public function apply(Builder $builder, Model $model): void
    {
        if ( ! empty($this->invitationData->roles)) {
            $builder->with(['roles' => function ($query) {
                $query->whereIn('name', $this->invitationData->roles);
            }]);
        }
    }

    public function __invoke(Folder $folder): void
    {
        $permissions = $this->invitationData->permissions;

        $this->collaboratorRepository->create(
            $folder->id,
            $this->invitationData->inviteeId,
            $this->invitationData->inviterId
        );

        if ($permissions->isNotEmpty()) {
            $this->permissions->create($this->invitationData->inviteeId, $folder->id, $permissions);
        }

        if ( ! empty($this->invitationData->roles)) {
            $records = $folder->roles->pluck(['id'])->map(fn (int $roleId) => [
                'collaborator_id' => $this->invitationData->inviteeId,
                'role_id'         => $roleId
            ]);

            FolderCollaboratorRole::insert($records->all());
        }
    }
}
