<?php

declare(strict_types=1);

namespace App\Http\Resources\Activity;

use App\Models\User;
use Illuminate\Support\Str;
use App\Models\FolderActivity;
use Illuminate\Http\Resources\Json\JsonResource;
use App\DataTransferObjects\Activities\CollaboratorSuspendedActivityLogData as ActivityLogData;

final class SuspensionActivityResource extends JsonResource
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

        $collaborator = $this->folderActivity->resources->findUserById($activityLog->suspendedBy->id);

        $suspendedCollaborator = $this->folderActivity->resources->findUserById($activityLog->suspendedCollaborator->id);

        return [
            'type'       => 'CollaboratorSuspendedActivity',
            'attributes' => [
                'event_time'   => $this->folderActivity->created_at,
                'message'      => $this->message(User::fromRequest($request), $suspendedCollaborator, $collaborator),
                'collaborator' => new UserResource($activityLog->suspendedCollaborator, $suspendedCollaborator),
                'suspended_by' => new UserResource($activityLog->suspendedBy, $collaborator),
            ]
        ];
    }

    private function message(User $authUser, User $suspendedCollaborator, User $collaborator): string
    {
        $activityLog = ActivityLogData::fromArray($this->folderActivity->data);

        $wasSuspendedByAuthUser = $authUser->id === $activityLog->suspendedBy->id;

        $suspendedCollaboratorIsAuthUser = $authUser->id === $activityLog->suspendedCollaborator->id;

        return str(':collaboratorName: suspended :suspendedCollaboratorName: :suspensionDuration:')

            ->when(
                value: $wasSuspendedByAuthUser,
                callback: fn ($message) => $message->replace(':collaboratorName:', 'You'),
                default: function ($message) use ($activityLog, $collaborator) {
                    return $message->replace(
                        ':collaboratorName:',
                        $collaborator->getFullNameOr($activityLog->suspendedBy)->present()
                    );
                }
            )

            ->when(
                value: $suspendedCollaboratorIsAuthUser,
                callback: fn ($message) => $message->replace(':suspendedCollaboratorName:', 'you'),
                default: function ($message) use ($activityLog, $suspendedCollaborator) {
                    return $message->replace(
                        ':suspendedCollaboratorName:',
                        $suspendedCollaborator->getFullNameOr($activityLog->suspendedCollaborator)->present()
                    );
                }
            )

            ->when(
                value: $activityLog->suspensionDurationInHours,
                callback: function ($message, int $duration) {
                    $hours = Str::plural('hour', $duration);

                    return $message->replace(':suspensionDuration:', "for {$duration} {$hours}");
                },
                default: fn ($message) => $message->replace(':suspensionDuration:', '')->squish()
            )

            ->toString();
    }
}
