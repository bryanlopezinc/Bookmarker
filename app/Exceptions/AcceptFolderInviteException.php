<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class AcceptFolderInviteException extends RuntimeException implements Responsable
{
    public function __construct(
        private readonly string $errorMessage,
        private readonly int $statusCode,
        private readonly string $info,
    ) {
    }

    public function report(): void
    {
    }

    public static function expiredOrInvalidInvitationToken(): self
    {
        return new self(
            'InvitationNotFoundOrExpired',
            JsonResponse::HTTP_NOT_FOUND,
            'The invitation token is expired or is invalid.'
        );
    }

    public static function inviteeAccountNoLongerExists(): self
    {
        return self::expiredOrInvalidInvitationToken();
    }

    public static function inviterAccountNoLongerExists(): self
    {
        return self::expiredOrInvalidInvitationToken();
    }

    public static function inviteeHasAlreadyAcceptedInvitation(): self
    {
        return new self(
            'InvitationAlreadyAccepted',
            JsonResponse::HTTP_CONFLICT,
            'The invitation has already been accepted.'
        );
    }

    /**
     * @inheritdoc
     */
    public function toResponse($request)
    {
        $data = [
            'message' => $this->errorMessage,
            'info' => $this->info
        ];

        return new JsonResponse($data, $this->statusCode);
    }
}
