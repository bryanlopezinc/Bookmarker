<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Filesystem\ProfileImagesFilesystem;
use App\Models\BlacklistedDomain;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

final class BlacklistedDomainResource extends JsonResource
{
    private readonly BlacklistedDomain $record;
    private readonly User $collaborator;
    private readonly ProfileImagesFilesystem $filesystem;

    public function __construct(BlacklistedDomain $record)
    {
        $collaborator  = $record->collaborator;

        $this->record = $record;
        $this->collaborator = $collaborator ??= new User();
        $this->filesystem =  new ProfileImagesFilesystem();
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        return [
            'type'       => 'BlacklistedDomain',
            'attributes' => [
                'id'                  => $this->record->public_id->present(),
                'domain'              => $this->record->resolved_domain,
                'blacklisted_at'      => (string) $this->record->created_at,
                'collaborator_exists' => $this->collaborator->exists,
                'collaborator'        => $this->when($this->collaborator->exists, fn () => [
                    'id'               => $this->collaborator->public_id->present(),
                    'name'             => $this->collaborator->full_name->present(),
                    'profile_image_url' => $this->filesystem->publicUrl($this->collaborator->profile_image_path),
                ])
            ]
        ];
    }
}
