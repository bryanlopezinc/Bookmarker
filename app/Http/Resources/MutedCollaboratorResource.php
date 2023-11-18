<?php

declare(strict_types=1);

namespace App\Http\Resources;

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
                'id'   => $this->collaborator->id,
                'name' => $this->collaborator->full_name,
            ]
        ];
    }
}
