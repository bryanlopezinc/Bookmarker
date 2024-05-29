<?php

declare(strict_types=1);

namespace App\Http\Resources\Activity;

use App\DataTransferObjects\Activities\InviteAcceptedActivityLogData as ActivityLogData;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\FolderActivity;
use App\Models\User;

final class NewCollaboratorActivityResource extends JsonResource
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

        $collaborator = $this->folderActivity->resources->findUserById($activityLog->inviter->id);

        $newCollaborator = $this->folderActivity->resources->findUserById($activityLog->invitee->id);

        return [
            'type'       => 'NewCollaboratorActivity',
            'attributes' => [
                'event_time'        => $this->folderActivity->created_at,
                'message'           => $this->message(User::fromRequest($request), $collaborator, $newCollaborator),
                'collaborator'      => new UserResource($activityLog->inviter, $collaborator),
                'new_collaborator'  => new UserResource($activityLog->invitee, $newCollaborator),
            ]
        ];
    }

    private function message(User $authUser, User $collaborator, User $newCollaborator): string
    {
        $activityLog = ActivityLogData::fromArray($this->folderActivity->data);

        $collaboratorWasAddedByAuthUser = $authUser->id === $activityLog->inviter->id;

        $newCollaboratorIsAuthUser = $authUser->id === $activityLog->invitee->id;

        return str(':collaboratorName: added :newCollaboratorName: as a new collaborator')

            ->when(
                value: $collaboratorWasAddedByAuthUser,
                callback: fn ($message) => $message->replace(':collaboratorName:', 'You'),
                default: function ($message) use ($activityLog, $collaborator) {
                    return $message->replace(
                        ':collaboratorName:',
                        $collaborator->getFullNameOr($activityLog->inviter)->present()
                    );
                }
            )

            ->when(
                value: $newCollaboratorIsAuthUser,
                callback: fn ($message) => $message->replace(':newCollaboratorName:', 'you'),
                default: function ($message) use ($activityLog, $newCollaborator) {
                    return $message->replace(
                        ':newCollaboratorName:',
                        $newCollaborator->getFullNameOr($activityLog->invitee)->present()
                    );
                }
            )

            ->toString();
    }
}
