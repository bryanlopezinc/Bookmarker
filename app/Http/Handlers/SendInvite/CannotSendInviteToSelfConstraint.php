<?php

declare(strict_types=1);

namespace App\Http\Handlers\SendInvite;

use App\DataTransferObjects\SendInviteRequestData;
use App\Exceptions\HttpException;
use App\Models\User;

final class CannotSendInviteToSelfConstraint
{
    public function __construct(private readonly SendInviteRequestData $requestData, private readonly User $invitee)
    {
    }

    public function __invoke(): void
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
