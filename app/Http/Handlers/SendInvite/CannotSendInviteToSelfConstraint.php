<?php

declare(strict_types=1);

namespace App\Http\Handlers\SendInvite;

use App\Contracts\FolderRequestHandlerInterface;
use App\DataTransferObjects\SendInviteRequestData;
use App\Exceptions\HttpException;
use App\Models\Folder;

final class CannotSendInviteToSelfConstraint implements FolderRequestHandlerInterface, InviteeAwareInterface
{
    use Concerns\HasInviteeData;

    public function __construct(private readonly SendInviteRequestData $requestData)
    {
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $isSendingInvitationToSelf = $this->invitee->id === $this->requestData->authUser->id;

        if ($isSendingInvitationToSelf) {
            throw HttpException::forbidden([
                'message' => 'CannotSendInviteToSelf',
                'info' => 'Cannot send invite to self.'
            ]);
        }
    }
}
