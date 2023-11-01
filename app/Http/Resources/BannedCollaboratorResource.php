<?php

declare(strict_types=1);

namespace App\Http\Resources;

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
                'id'          => $this->bannedCollaborator->id,
                'first_name'  => $this->bannedCollaborator->first_name,
                'last_name'   => $this->bannedCollaborator->last_name,
            ]
        ];
    }
}
