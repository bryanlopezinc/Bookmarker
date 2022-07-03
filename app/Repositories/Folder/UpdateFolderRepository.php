<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\DataTransferObjects\Folder;
use App\Models\Folder as Model;
use App\Repositories\TagsRepository;
use App\ValueObjects\ResourceID;

final class UpdateFolderRepository
{
    public function update(ResourceID $folderID, Folder $newAttributes): void
    {
        $folder = Model::query()->whereKey($folderID->toInt())->first();

        $folder->update([
            'description' => $newAttributes->description->isEmpty() ? null : $newAttributes->description->value,
            'name' => $newAttributes->name->value,
            'is_public' => $newAttributes->isPublic
        ]);

        (new TagsRepository)->attach($newAttributes->tags, $folder);
    }
}
