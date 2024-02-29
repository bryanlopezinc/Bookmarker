<?php

declare(strict_types=1);

namespace App\Http\Handlers\SendInvite;

use App\Contracts\FolderRequestHandlerInterface;
use App\DataTransferObjects\SendInviteRequestData;
use App\Exceptions\HttpException;
use App\Models\Folder;
use Illuminate\Http\Response;

final class CollaboratorCannotSendInviteWithPermissionsConstraint implements FolderRequestHandlerInterface
{
    public function __construct(private readonly SendInviteRequestData $data)
    {
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $folderBelongsToAuthUser = $folder->user_id === $this->data->authUser->id;

        if ($folderBelongsToAuthUser) {
            return;
        }

        if ($this->data->permissionsToBeAssigned->isNotEmpty()) {
            throw new HttpException([
                'message' => 'CollaboratorCannotSendInviteWithPermissions',
                'info' => 'Folder collaborator cannot send invites with permissions.'
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
