<?php

declare(strict_types=1);

namespace App\Http\Resources\Activity;

use App\Models\User;
use Illuminate\Support\Str;
use App\Models\FolderActivity;
use Illuminate\Http\Resources\Json\JsonResource;
use App\DataTransferObjects\Activities\FolderBookmarksRemovedActivityLogData as ActivityLogData;

final class FolderBookmarksRemovedActivityResource extends JsonResource
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
            'type'       => 'FolderBookmarksRemovedActivity',
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

        $bookmarksWasRemovedByAuthUser = $authUser->id === $activityLog->collaborator->id;

        $bookmarkWord = Str::plural('bookmark', $activityLog->bookmarks);

        return str(":collaboratorName: removed {$activityLog->bookmarks->count()} {$bookmarkWord}")

            ->when(
                value: $bookmarksWasRemovedByAuthUser,
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
