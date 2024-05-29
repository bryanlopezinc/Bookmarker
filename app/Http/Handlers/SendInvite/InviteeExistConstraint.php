<?php

declare(strict_types=1);

namespace App\Http\Handlers\SendInvite;

use App\Exceptions\HttpException;
use App\Models\User;

final class InviteeExistConstraint
{
    public function __construct(private readonly User $invitee)
    {
    }

    public function __invoke(): void
    {
        if ( ! $this->invitee->exists) {
            throw HttpException::notFound([
                'message' => 'InviteeNotFound',
                'info' => 'A user with the given email could not be found.'
            ]);
        }
    }
}
