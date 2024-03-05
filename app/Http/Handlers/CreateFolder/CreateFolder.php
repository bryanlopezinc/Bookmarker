<?php

declare(strict_types=1);

namespace App\Http\Handlers\CreateFolder;

use App\DataTransferObjects\CreateFolderData;
use App\Enums\FolderVisibility;
use App\Models\Folder;
use App\ValueObjects\FolderSettings;

final class CreateFolder implements HandlerInterface
{
    public function create(CreateFolderData $data): void
    {
        Folder::create([
            'description' => $data->description,
            'name'        => $data->name,
            'user_id'     => $data->owner->id,
            'visibility'  => FolderVisibility::fromRequest($data->visibility),
            'settings'    => new FolderSettings($data->settings),
            'password'    => $data->password
        ]);
    }
}
