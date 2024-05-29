<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Activities;

use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;

final class CollaboratorSuspendedActivityLogData implements Arrayable
{
    public function __construct(
        public readonly User $suspendedCollaborator,
        public readonly User $suspendedBy,
        public readonly ?int $suspensionDurationInHours = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        $suspendedCollaborator = new User($data['collaborator']);
        $suspendedBy = new User($data['suspended_by']);

        $suspendedBy->exists = true;
        $suspendedCollaborator->exists = true;

        return new CollaboratorSuspendedActivityLogData(
            $suspendedCollaborator,
            $suspendedBy,
            $data['suspension_duration_in_hours']
        );
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return [
            'version'                      => '1.0.0',
            'suspension_duration_in_hours' => $this->suspensionDurationInHours,
            'collaborator'                 => $this->suspendedCollaborator->activityLogContextVariables(),
            'suspended_by'                 => $this->suspendedBy->activityLogContextVariables()
        ];
    }
}
