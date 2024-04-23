<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Filesystem\ProfileImageFileSystem;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

final class BannedCollaboratorResource extends JsonResource
{
    public function __construct(private User $bannedCollaborator)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        return [
            'type' => 'bannedCollaborator',
            'attributes' => [
                'id'    => $this->bannedCollaborator->public_id->present(),
                'name'  => $this->bannedCollaborator->full_name->present(),
                'profile_image_url' => (new ProfileImageFileSystem())->publicUrl($this->bannedCollaborator->profile_image_path)
            ]
        ];
    }
}
