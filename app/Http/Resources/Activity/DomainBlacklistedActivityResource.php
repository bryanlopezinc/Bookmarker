<?php

declare(strict_types=1);

namespace App\Http\Resources\Activity;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\FolderActivity;
use App\Models\User;
use App\DataTransferObjects\Activities\DomainBlacklistedActivityLogData as ActivityLogData;

final class DomainBlacklistedActivityResource extends JsonResource
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

        $domain = $activityLog->url->getHost();

        return [
            'type'       => 'DomainBlacklistedActivity',
            'attributes' => [
                'event_time'   => $this->folderActivity->created_at,
                'message'      => $this->message(User::fromRequest($request), $collaborator, $domain),
                'collaborator' => new UserResource($activityLog->collaborator, $collaborator),
            ]
        ];
    }

    private function message(User $authUser, User $collaborator, string $domain): string
    {
        $activityLog = ActivityLogData::fromArray($this->folderActivity->data);

        $wasBlacklistedByAuthUser = $authUser->id === $activityLog->collaborator->id;

        return str(":collaboratorName: restricted bookmarks from {$domain}.")

            ->when(
                value: $wasBlacklistedByAuthUser,
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
