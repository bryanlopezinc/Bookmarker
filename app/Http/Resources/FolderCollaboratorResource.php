<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DataTransferObjects\FolderCollaborator;
use App\Filesystem\ProfileImageFileSystem;
use Illuminate\Http\Resources\Json\JsonResource;

final class FolderCollaboratorResource extends JsonResource
{
    public function __construct(private readonly FolderCollaborator $collaborator)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $filesystem = new ProfileImageFileSystem();

        $inviterExists = $this->collaborator->wasInvitedBy !== null;
        $wasInvitedByAuthUser = $this->collaborator->wasInvitedBy?->id === auth()->id();

        return [
            'type' => 'folderCollaborator',
            'attributes' => [
                'id'          => $this->collaborator->user->id,
                'name'        => $this->collaborator->user->full_name,
                'permissions' => $this->collaborator->permissions->toExternalIdentifiers(),
                'profile_image_url'  => $filesystem->publicUrl($this->collaborator->user->profile_image_path),
                'added_by'    => [
                    'exists'       => $inviterExists,
                    'is_auth_user' => $wasInvitedByAuthUser,
                    'user'         => $this->when($inviterExists && !$wasInvitedByAuthUser, [
                        'id'   =>  $this->collaborator->wasInvitedBy?->id,
                        'name' => $this->collaborator->wasInvitedBy?->full_name,
                        'profile_image_url' => $filesystem->publicUrl($this->collaborator->wasInvitedBy?->profile_image_path),
                    ])
                ]
            ]
        ];
    }
}
