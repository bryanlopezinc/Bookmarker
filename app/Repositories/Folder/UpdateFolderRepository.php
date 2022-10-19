<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\DataTransferObjects\Folder;
use App\Models\Folder as Model;
use App\Repositories\TagRepository;
use App\ValueObjects\ResourceID;

final class UpdateFolderRepository
{
    public function update(ResourceID $folderID, Folder $newAttributes): void
    {
        /** @var Model|null */
        $folder = Model::query()->whereKey($folderID->value())->first();

        if ($folder === null) {
            throw new \Exception('Folder does not exist');
        }

        $folder->update([
            'description' => $newAttributes->description->isEmpty() ? null : $newAttributes->description->value,
            'name' => $newAttributes->name->value,
            'is_public' => $newAttributes->isPublic
        ]);

        (new TagRepository)->attach($newAttributes->tags, $folder);
    }
}
