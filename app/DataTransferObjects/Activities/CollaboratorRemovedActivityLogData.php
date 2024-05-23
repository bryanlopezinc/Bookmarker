<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Activities;

use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;

final class CollaboratorRemovedActivityLogData implements Arrayable
{
    public function __construct(
        public readonly User $collaboratorRemoved,
        public readonly User $collaborator,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $collaboratorRemoved = new User($data['collaborator_removed']);
        $collaborator = new User($data['collaborator']);

        $collaborator->exists = true;
        $collaboratorRemoved->exists = true;

        return new CollaboratorRemovedActivityLogData($collaboratorRemoved, $collaborator);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return [
            'version'              => '1.0.0',
            'collaborator_removed' => $this->collaboratorRemoved->activityLogContextVariables(),
            'collaborator'         => $this->collaborator->activityLogContextVariables()
        ];
    }
}
