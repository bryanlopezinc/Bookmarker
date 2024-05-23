<?php

declare(strict_types=1);

namespace App\Http\Resources\Activity;

use App\DataTransferObjects\Activities\FolderIconChangedActivityLogData as ActivityLogData;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\FolderActivity;
use App\Models\User;

final class FolderIconChangedActivityResource extends JsonResource
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
            'type'       => 'FolderIconChangedActivity',
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

        $wasChangedByAuthUser = $authUser->id === $activityLog->collaborator->id;

        return str(':collaboratorName: changed folder icon')

            ->when(
                value: $wasChangedByAuthUser,
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
