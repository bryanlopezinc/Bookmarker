<?php

declare(strict_types=1);

namespace App\Http\Resources\Activity;

use App\DataTransferObjects\Activities\FolderNameChangedActivityLogData as ActivityLogData;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\FolderActivity;
use App\Models\User;
use App\ValueObjects\FolderName;

final class FolderNameChangedActivityResource extends JsonResource
{
    public function __construct(private readonly FolderActivity $folderActivity)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $activityLog = ActivityLogData::fromArray($this->folderActivity->data);

        $collaborator = $this->folderActivity->resources->findUserById($activityLog->collaborator->id);

        return [
            'type'       => 'FolderNameChangedActivity',
            'attributes' => [
                'event_time'   => $this->folderActivity->created_at,
                'message'      => $this->message(User::fromRequest($request), $collaborator),
                'collaborator' => new UserResource($activityLog->collaborator, $collaborator),
            ]
        ];
    }

    private function message(User $authUser, User $collaborator): string
    {
        $activityLog = ActivityLogData::fromArray($this->folderActivity->data);

        $nameWasChangedByAuthUser = $authUser->id === $activityLog->collaborator->id;

        $oldName = new FolderName($activityLog->oldName);
        $newName = new FolderName($activityLog->newName);

        return str(":collaboratorName: changed folder name from {$oldName->present()} to {$newName->present()}")

            ->when(
                value: $nameWasChangedByAuthUser,
                callback: fn ($message) => $message->replace(':collaboratorName:', 'You'),
                default: function ($message) use ($activityLog, $collaborator) {
                    return $message->replace(
                        ':collaboratorName:',
                        $collaborator->getFullNameOr($activityLog->collaborator)->present()
                    );
                }
            )

            ->toString();
    }
}
