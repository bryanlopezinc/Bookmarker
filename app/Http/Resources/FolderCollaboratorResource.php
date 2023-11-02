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
                'id'          => $this->folderCollaborator->user->id,
                'name'        => $this->folderCollaborator->user->full_name,
                'permissions' => $this->folderCollaborator->permissions->toJsonResponse()
            ]
        ];
    }
}
