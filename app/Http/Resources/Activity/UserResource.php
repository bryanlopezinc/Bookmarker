<?php

declare(strict_types=1);

namespace App\Http\Resources\Activity;

use App\Filesystem\ProfileImagesFilesystem;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;

final class UserResource extends JsonResource
{
    public function __construct(
        private readonly User $potentiallyOutdatedUserRecord,
        private readonly User $currentUserRecord
    ) {
        parent::__construct($currentUserRecord);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $filesystem = new ProfileImagesFilesystem();

        return [
            'exists' => $this->currentUserRecord->exists,
            'id'     => $this->potentiallyOutdatedUserRecord->public_id->present(),
            'avatar' => $filesystem->publicUrl($this->currentUserRecord->getProfileImagePathOr($this->potentiallyOutdatedUserRecord)),
        ];
    }
}
