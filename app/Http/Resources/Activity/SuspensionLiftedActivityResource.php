<?php

declare(strict_types=1);

namespace App\Http\Resources\Activity;

use App\DataTransferObjects\Activities\SuspensionLiftedActivityLogData as ActivityLogData;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\FolderActivity;
use App\Models\User;

final class SuspensionLiftedActivityResource extends JsonResource
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

        $collaborator = $this->folderActivity->resources->findUserById($activityLog->reinstatedBy->id);

        $affectedCollaborator = $this->folderActivity->resources->findUserById($activityLog->suspendedCollaborator->id);

        return [
            'type'       => 'SuspensionLiftedActivity',
            'attributes' => [
                'event_time'            => $this->folderActivity->created_at,
                'message'               => $this->message(User::fromRequest($request), $collaborator, $affectedCollaborator),
                'collaborator'          => new UserResource($activityLog->reinstatedBy, $collaborator),
                'affected_collaborator' => new UserResource($activityLog->suspendedCollaborator, $affectedCollaborator),
            ]
        ];
    }

    private function message(User $authUser, User $collaborator, User $affectedCollaborator): string
    {
        $activityLog = ActivityLogData::fromArray($this->folderActivity->data);

        $collaboratorWasUnSuspendedByAuthUser = $authUser->id === $activityLog->reinstatedBy->id;

        $affectedCollaboratorIsAuthUser = $authUser->id === $activityLog->suspendedCollaborator->id;

        return str(':affectedCollaboratorName: suspension was lifted by :collaboratorName:')

            ->when(
                value: $collaboratorWasUnSuspendedByAuthUser,
                callback: fn ($message) => $message->replace(':collaboratorName:', 'you'),
                default: function ($message) use ($activityLog, $collaborator) {
                    return $message->replace(
                        ':collaboratorName:',
                        $collaborator->getFullNameOr($activityLog->reinstatedBy)->present()
                    );
                }
            )

            ->when(
                value: $affectedCollaboratorIsAuthUser,
                callback: fn ($message) => $message->replace(':affectedCollaboratorName:', 'Your'),
                default: function ($message) use ($activityLog, $affectedCollaborator) {
                    return $message->replace(
                        ':affectedCollaboratorName:',
                        $affectedCollaborator->getFullNameOr($activityLog->suspendedCollaborator)->present()
                    );
                }
            )

            ->toString();
    }
}
