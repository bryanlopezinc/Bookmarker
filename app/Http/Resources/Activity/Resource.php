<?php

declare(strict_types=1);

namespace App\Http\Resources\Activity;

use App\Enums\ActivityType as Type;
use App\Models\FolderActivity;
use Illuminate\Http\Resources\Json\JsonResource;

final class Resource extends JsonResource
{
    public function __construct(private readonly FolderActivity $folderActivity)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        return match ($this->folderActivity->type) {
            Type::NEW_BOOKMARKS        => new NewFolderBookmarksActivityResource($this->folderActivity),
            Type::USER_SUSPENDED       => new SuspensionActivityResource($this->folderActivity),
            Type::SUSPENSION_LIFTED    => new SuspensionLiftedActivityResource($this->folderActivity),
            Type::COLLABORATOR_REMOVED => new CollaboratorRemovedActivityResource($this->folderActivity),
            Type::NAME_CHANGED         => new FolderNameChangedActivityResource($this->folderActivity),
            Type::ICON_CHANGED         => new FolderIconChangedActivityResource($this->folderActivity),
            Type::DESCRIPTION_CHANGED  => new FolderDescriptionChangedActivityResource($this->folderActivity),
            TYPE::COLLABORATOR_EXIT    => new CollaboratorExitActivity($this->folderActivity),
            Type::BOOKMARKS_REMOVED    => new FolderBookmarksRemovedActivityResource($this->folderActivity),
            Type::FOLDER_VISIBILITY_CHANGED_TO_COLLABORATORS_ONLY => new FolderVisibilityChangedToCollaboratorsOnlyResource($this->folderActivity),
            Type::FOLDER_VISIBILITY_CHANGED_TO_PUBLIC => new FolderVisibilityChangedToPublicResource($this->folderActivity),
            default                    => new NewCollaboratorActivityResource($this->folderActivity)
        };
    }
}
