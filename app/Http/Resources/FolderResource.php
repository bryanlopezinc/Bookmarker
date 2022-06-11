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
                'description' => $this->folder->description->safe(),
                'date_created' => $this->folder->createdAt->toDateTimeString(),
                'last_updated' => $this->folder->updatedAt->toDateTimeString(),
                'items_count' => $this->folder->bookmarksCount->value,
            ]
        ];
    }
}
