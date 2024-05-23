<?php

declare(strict_types=1);

namespace App\Http\Resources\ResourceMessages;

use App\DataTransferObjects\Activities\FolderIconChangedActivityLogData;
use App\Models\User;
use JsonSerializable;

final class IconChanged implements JsonSerializable
{
    public function __construct(
        private readonly User $collaborator,
        private readonly User $authUser,
        private readonly FolderIconChangedActivityLogData $activityLog
    ) {
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize(): mixed
    {
        $activityLog = $this->activityLog;

        $wasChangedByAuthUser = $this->authUser->exists && $this->authUser->id === $activityLog->collaborator->id;

        return str(':collaboratorName: changed folder icon')

            ->when(
                value: $wasChangedByAuthUser,
                callback: fn ($message) => $message->replace(':collaboratorName:', 'You'),
                default: function ($message) use ($activityLog) {
                    return $message->replace(
                        ':collaboratorName:',
                        $this->collaborator->getFullNameOr($activityLog->collaborator)->present()
                    );
                }
            )

            ->toString();
    }
}
