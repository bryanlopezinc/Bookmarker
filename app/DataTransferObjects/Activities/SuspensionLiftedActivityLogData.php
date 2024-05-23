<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Activities;

use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;

final class SuspensionLiftedActivityLogData implements Arrayable
{
    public function __construct(
        public readonly User $suspendedCollaborator,
        public readonly User $reinstatedBy,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $suspendedCollaborator = new User($data['suspended_collaborator']);
        $reinstatedBy = new User($data['reinstated_by']);

        $suspendedCollaborator->exists = true;
        $reinstatedBy->exists = true;

        return new SuspensionLiftedActivityLogData($suspendedCollaborator, $reinstatedBy);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return [
            'version'                => '1.0.0',
            'suspended_collaborator' => $this->suspendedCollaborator->activityLogContextVariables(),
            'reinstated_by'          => $this->reinstatedBy->activityLogContextVariables()
        ];
    }
}
