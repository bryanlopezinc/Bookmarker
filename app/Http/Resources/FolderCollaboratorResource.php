<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DataTransferObjects\FolderCollaborator;
use Illuminate\Http\Resources\Json\JsonResource;

final class FolderCollaboratorResource extends JsonResource
{
    public function __construct(private readonly FolderCollaborator $folderCollaborator)
    {
        parent::__construct($folderCollaborator);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        return [
            'type' => 'folderCollaborator',
            'attributes' => [
                'id' => $this->folderCollaborator->user->id->value(),
                'firstname' => $this->folderCollaborator->user->firstName->value,
                'lastname' => $this->folderCollaborator->user->lastName->value,
                'permissions' => [
                    'canInviteUsers' => $this->folderCollaborator->permissions->canInviteUser(),
                    'canAddBookmarks' => $this->folderCollaborator->permissions->canAddBookmarks(),
                    'canRemoveBookmarks' => $this->folderCollaborator->permissions->canRemoveBookmarks(),
                    'canUpdateFolder' => $this->folderCollaborator->permissions->canUpdateFolder()
                ],
            ]
        ];
    }
}
