<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Folder;
use App\ValueObjects\FolderStorage;
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
        $storage = new FolderStorage($this->folder->bookmarks_count);

        return [
            'type'       => 'folder',
            'attributes' => [
                'id'                  => $this->folder->public_id->present(),
                'name'                => $this->folder->name->present(),
                'has_description'     => $this->folder->description !== null,
                'description'         => $this->when($this->folder->description !== null, $this->folder->description),
                'date_created'        => $this->folder->created_at->toDateTimeString(),
                'last_updated'        => $this->folder->updated_at->toDateTimeString(),
                'visibility'          => $this->folder->visibility->toWord(),
                'collaborators_count' => $this->folder->collaborators_count,
                'storage' => [
                    'items_count'     => $storage->total,
                    'capacity'        => $storage::MAX_ITEMS,
                    'is_full'         => $storage->isFull(),
                    'available'       => $storage->spaceAvailable(),
                    'percentage_used' => $storage->percentageUsed(),
                ]
            ]
        ];
    }
}
