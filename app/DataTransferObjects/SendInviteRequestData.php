<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Models\User;
use App\UAC;
use Illuminate\Http\Request;

final class SendInviteRequestData
{
    public function __construct(
        public readonly User $authUser,
        public readonly string $inviteeEmail,
        public readonly UAC $permissionsToBeAssigned,
        public readonly array $roles,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            User::fromRequest($request),
            $request->input('email'),
            UAC::fromRequest($request, 'permissions'),
            $request->input('roles', [])
        );
    }
}
