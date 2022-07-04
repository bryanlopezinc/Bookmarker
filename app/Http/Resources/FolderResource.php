<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DataTransferObjects\Folder;
use Illuminate\Http\Resources\Json\JsonResource;

final class FolderResource extends JsonResource
{
    public function __construct(private Folder $folder)
    {
        parent::__construct($folder);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        return [
            'type' => 'folder',
            'attributes' => [
                'id' => $this->folder->folderID->toInt(),
                'name' => $this->folder->name->safe(),
                'has_description' => !$this->folder->description->isEmpty(),
                'description' => $this->when(!$this->folder->description->isEmpty(), fn () => $this->folder->description->safe()),
                'date_created' => $this->folder->createdAt->toDateTimeString(),
                'last_updated' => $this->folder->updatedAt->toDateTimeString(),
                'is_public' => $this->folder->isPublic,
                'tags' => $this->folder->tags->toStringCollection()->all(),
                'has_tags' => $this->folder->tags->isNotEmpty(),
                'tags_count' => $this->folder->tags->count(),
                'storage' => [
                    'items_count' => $this->folder->storage->total,
                    'capacity' => $this->folder->storage::MAX_ITEMS,
                    'is_full' => $this->folder->storage->isFull(),
                    'available' => $this->folder->storage->spaceAvailable(),
                    'percentage_used' => $this->folder->storage->percentageUsed(),
                ]
            ]
        ];
    }
}
