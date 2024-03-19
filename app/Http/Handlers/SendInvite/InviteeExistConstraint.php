<?php

declare(strict_types=1);

namespace App\Http\Handlers\SendInvite;

use App\Contracts\FolderRequestHandlerInterface;
use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\User;

final class InviteeExistConstraint implements FolderRequestHandlerInterface
{
    public function __construct(private readonly User $invitee)
    {
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        if ( ! $this->invitee->exists) {
            throw HttpException::notFound([
                'message' => 'InviteeNotFound',
                'info' => 'A user with the given email could not be found.'
            ]);
        }
    }
}
