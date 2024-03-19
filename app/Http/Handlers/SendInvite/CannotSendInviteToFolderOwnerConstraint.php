<?php

declare(strict_types=1);

namespace App\Http\Handlers\SendInvite;

use App\Contracts\FolderRequestHandlerInterface;
use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\User;

final class CannotSendInviteToFolderOwnerConstraint implements FolderRequestHandlerInterface
{
    public function __construct(private readonly User $invitee)
    {
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $isSendingInviteToFolderOwner = $folder->user_id === $this->invitee->id;

        if ($isSendingInviteToFolderOwner) {
            throw HttpException::conflict([
                'message' => 'UserAlreadyACollaborator',
                'info' => 'Request could not be completed because the user is already a collaborator.'
            ]);
        }
    }
}
