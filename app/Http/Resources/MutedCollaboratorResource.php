<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Filesystem\ProfileImagesFilesystem;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

final class MutedCollaboratorResource extends JsonResource
{
    public function __construct(private readonly User $collaborator)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        return [
            'type' => 'mutedCollaborator',
            'attributes' => [
                'id'   => $this->collaborator->public_id->present(),
                'name' => $this->collaborator->full_name->present(),
                'profile_image_url' => (new ProfileImagesFilesystem())->publicUrl($this->collaborator->profile_image_path),
            ]
        ];
    }
}
