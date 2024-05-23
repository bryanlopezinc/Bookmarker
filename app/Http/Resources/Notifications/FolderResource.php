<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\Models\Folder;
use Illuminate\Http\Resources\Json\JsonResource;

final class FolderResource extends JsonResource
{
    public function __construct(
        private readonly Folder $potentiallyOutdatedFolderRecord,
        private readonly Folder $currentFolderRecord
    ) {
        parent::__construct($currentFolderRecord);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        return [
            'exists' => $this->currentFolderRecord->exists,
            'id'     => $this->potentiallyOutdatedFolderRecord->public_id->present(),
        ];
    }
}
