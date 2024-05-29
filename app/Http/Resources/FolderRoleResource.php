<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FolderRole;
use Illuminate\Http\Resources\Json\JsonResource;

final class FolderRoleResource extends JsonResource
{
    public function __construct(private FolderRole $role)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        return [
            'type' => 'FolderRole',
            'attributes' => [
                'id'                  => $this->role->public_id->present(),
                'name'                => $this->role->name,
                'created_at'          => $this->role->created_at->toDateTimeString(),
                'permissions'         => $this->role->permissionNames,
                'collaborators_count' => $this->role->assigneesCount
            ]
        ];
    }
}
