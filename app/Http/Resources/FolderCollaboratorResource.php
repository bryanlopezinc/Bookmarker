<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DataTransferObjects\FolderCollaborator;
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
        $inviterExists = $this->collaborator->wasInvitedBy !== null;

        $wasInvitedByAuthUser = $this->collaborator->wasInvitedBy?->id === auth()->id();

        return [
            'type' => 'folderCollaborator',
            'attributes' => [
                'id'          => $this->collaborator->user->id,
                'name'        => $this->collaborator->user->full_name,
                'permissions' => $this->collaborator->permissions->toJsonResponse(),
                'added_by'    => [
                    'exists'       => $inviterExists,
                    'is_auth_user' => $wasInvitedByAuthUser,
                    'user'         => $this->when($inviterExists && !$wasInvitedByAuthUser, [
                        'id'   =>  $this->collaborator->wasInvitedBy?->id,
                        'name' => $this->collaborator->wasInvitedBy?->full_name
                    ])
                ]
            ]
        ];
    }
}
