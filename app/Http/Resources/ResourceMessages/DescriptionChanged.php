<?php

declare(strict_types=1);

namespace App\Http\Resources\ResourceMessages;

use App\DataTransferObjects\Activities\DescriptionChangedActivityLogData;
use App\Models\User;
use JsonSerializable;

final class DescriptionChanged implements JsonSerializable
{
    public function __construct(
        private readonly User $collaborator,
        private readonly User $authUser,
        private readonly DescriptionChangedActivityLogData $activityLog
    ) {
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize(): mixed
    {
        $activityLog = $this->activityLog;

        $wasChangedByAuthUser = $this->authUser->exists && $this->authUser->id === $activityLog->collaborator->id;

        return str(':collaboratorName: :changedOrRemoved: folder description :old: :new:')

            ->when(
                value: $activityLog->descriptionHasOldValue() && $activityLog->descriptionHasNewValue(),
                callback: function ($message) use ($activityLog) {
                    return $message
                        ->replace(':changedOrRemoved:', 'changed')
                        ->replace(':old:', "from {$activityLog->oldDescription}")
                        ->replace(':new:', "to {$activityLog->newDescription}");
                },
            )

            ->when(
                value: $activityLog->descriptionWasChangedFromBlankToFilled(),
                callback: function ($message) use ($activityLog) {
                    return $message
                        ->replace(':changedOrRemoved:', 'changed')
                        ->replace(':old:', '')
                        ->replace(':new:', "to {$activityLog->newDescription}");
                },
            )

            ->when(
                value: $activityLog->descriptionWasRemoved(),
                callback: function ($message) {
                    return $message
                        ->replace(':changedOrRemoved:', 'removed')
                        ->replace(':old:', '')
                        ->replace(':new:', '');
                },
            )

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

            ->squish()
            ->toString();
    }
}
