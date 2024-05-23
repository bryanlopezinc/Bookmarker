<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Filesystem\ProfileImagesFilesystem;
use App\Models\SuspendedCollaborator;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

final class SuspendedCollaboratorResource extends JsonResource
{
    private readonly SuspendedCollaborator $record;
    private readonly User $collaborator;
    private readonly User $suspendedBy;
    private readonly ProfileImagesFilesystem $filesystem;

    public function __construct(SuspendedCollaborator $record)
    {
        $this->record = $record;
        $this->collaborator = $record->collaborator;
        $this->suspendedBy = $record->suspendedByUser;
        $this->filesystem =  new ProfileImagesFilesystem();
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $authUser = User::fromRequest($request);

        return [
            'type'       => 'SuspendedCollaborator',
            'attributes' => [
                'id'                         => $this->collaborator->public_id->present(),
                'name'                       => $this->collaborator->full_name->present(),
                'profile_image_url'           => $this->filesystem->publicUrl($this->collaborator->profile_image_path),
                'suspended_at'               => (string) $this->record->suspended_at,
                'suspended_until'            => $this->when($this->record->suspended_until !== null, $this->record->suspended_until?->toDateTimeString()),
                'is_suspended_indefinitely'   => $this->record->duration_in_hours === null,
                'suspension_period_is_past'  => $this->record->suspensionPeriodIsPast(),
                'was_suspended_by_auth_user' => $this->record->suspended_by === $authUser->id,
                'suspended_by'               => [
                    'id'                        => $this->suspendedBy->public_id->present(),
                    'name'                      => $this->suspendedBy->full_name->present(),
                    'profile_image_url'          => $this->filesystem->publicUrl($this->suspendedBy->profile_image_path),
                ]
            ]
        ];
    }
}
