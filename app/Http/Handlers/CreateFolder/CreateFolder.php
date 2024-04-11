<?php

declare(strict_types=1);

namespace App\Http\Handlers\CreateFolder;

use App\DataTransferObjects\CreateFolderData;
use App\Enums\FolderVisibility;
use App\Filesystem\FolderThumbnailFileSystem;
use App\Models\Folder;
use App\ValueObjects\FolderSettings;

final class CreateFolder implements HandlerInterface
{
    private readonly FolderThumbnailFileSystem $filesystem;

    public function __construct(FolderThumbnailFileSystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?: new FolderThumbnailFileSystem();
    }

    public function create(CreateFolderData $data): void
    {
        $iconPath = null;

        if ($data->thumbnail !== null) {
            $iconPath = $this->filesystem->store($data->thumbnail);
        }

        Folder::create([
            'description' => $data->description,
            'name'        => $data->name,
            'user_id'     => $data->owner->id,
            'visibility'  => FolderVisibility::fromRequest($data->visibility),
            'settings'    => new FolderSettings($data->settings),
            'password'    => $data->password,
            'icon_path'   => $iconPath
        ]);
    }
}
