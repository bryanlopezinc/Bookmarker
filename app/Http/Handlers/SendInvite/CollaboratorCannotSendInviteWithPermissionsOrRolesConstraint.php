<?php

declare(strict_types=1);

namespace App\Http\Handlers\SendInvite;

use App\DataTransferObjects\SendInviteRequestData;
use App\Exceptions\HttpException;
use App\Models\Folder;
use Illuminate\Http\Response;

final class CollaboratorCannotSendInviteWithPermissionsOrRolesConstraint
{
    public function __construct(private readonly SendInviteRequestData $data)
    {
    }

    public function __invoke(Folder $folder): void
    {
        $folderBelongsToAuthUser = $folder->user_id === $this->data->authUser->id;

        if ($folderBelongsToAuthUser) {
            return;
        }

        if ($this->data->permissionsToBeAssigned->isNotEmpty() || ! empty($this->data->roles)) {
            throw new HttpException([
                'message' => 'CollaboratorCannotSendInviteWithPermissionsOrRoles',
                'info'    => 'Folder collaborator cannot send invites with permissions or roles.'
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
