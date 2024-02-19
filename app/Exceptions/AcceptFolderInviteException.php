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

    public static function dueToExpiredOrInvalidInvitationToken(): self
    {
        return new self(
            'InvitationNotFoundOrExpired',
            JsonResponse::HTTP_NOT_FOUND,
            'The invitation token is expired or is invalid.'
        );
    }

    public static function dueToFolderNotFound(): self
    {
        return new self(
            (new FolderNotFoundException())->message,
            JsonResponse::HTTP_NOT_FOUND,
            'The folder could not be found.'
        );
    }

    public static function dueToInviteeAccountNoLongerExists(): self
    {
        return self::dueToExpiredOrInvalidInvitationToken();
    }

    public static function dueToInviterAccountNoLongerExists(): self
    {
        return self::dueToExpiredOrInvalidInvitationToken();
    }

    public static function dueToFolderCollaboratorsLimitReached(): self
    {
        return new self(
            'MaxCollaboratorsLimitReached',
            JsonResponse::HTTP_FORBIDDEN,
            'Folder has reached its max collaborators limit.'
        );
    }

    public static function dueToPrivateFolder(): self
    {
        return new self(
            'FolderIsMarkedAsPrivate',
            JsonResponse::HTTP_FORBIDDEN,
            'Folder has been marked as private by owner.'
        );
    }

    public static function dueToPasswordProtectedFolder(): self
    {
        return new self(
            'FolderIsPasswordProtected',
            JsonResponse::HTTP_FORBIDDEN,
            'Folder has been marked as protected by owner.'
        );
    }

    public static function dueToInviteeHasAlreadyAcceptedInvitation(): self
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
