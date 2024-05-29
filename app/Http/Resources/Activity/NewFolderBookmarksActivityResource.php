<?php

declare(strict_types=1);

namespace App\Http\Resources\Activity;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\FolderActivity;
use App\Models\User;
use App\DataTransferObjects\Activities\NewFolderBookmarksActivityLogData as ActivityLogData;

final class NewFolderBookmarksActivityResource extends JsonResource
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
            'type'       => 'NewFolderBookmarksActivity',
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

        $bookmarksWasAddedByAuthUser = $authUser->id === $activityLog->collaborator->id;

        return str(":collaboratorName: added {$activityLog->bookmarks->count()} new :singularOrPlural:")

            ->when(
                value: $bookmarksWasAddedByAuthUser,
                callback: fn ($message) => $message->replace(':collaboratorName:', 'You'),
                default: function ($message) use ($activityLog, $collaborator) {
                    return $message->replace(
                        ':collaboratorName:',
                        $collaborator->getFullNameOr($activityLog->collaborator)->present()
                    );
                }
            )

            ->when(
                value: $activityLog->bookmarks->count() === 1,
                callback: fn ($message) => $message->replace(':singularOrPlural:', 'bookmark'),
                default: fn ($message) => $message->replace(':singularOrPlural:', 'bookmarks'),
            )

            ->toString();
    }
}
