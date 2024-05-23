<?php

declare(strict_types=1);

namespace App\Http\Handlers\CreateFolder;

use App\Contracts\IdGeneratorInterface;
use App\DataTransferObjects\CreateFolderData;
use App\Enums\FolderVisibility;
use App\Filesystem\FoldersIconsFilesystem;
use App\FolderSettings\FolderSettings;
use App\Models\Folder;

final class CreateFolder
{
    private readonly FoldersIconsFilesystem $filesystem;
    private readonly IdGeneratorInterface $IdGenerator;

    public function __construct(FoldersIconsFilesystem $filesystem = null, IdGeneratorInterface $IdGenerator = null)
    {
        $this->filesystem = $filesystem ?: new FoldersIconsFilesystem();
        $this->IdGenerator = $IdGenerator ??= app(IdGeneratorInterface::class);
    }

    public function create(CreateFolderData $data): void
    {
        $iconPath = null;

        if ($data->icon !== null) {
            $iconPath = $this->filesystem->store($data->icon);
        }

        Folder::create([
            'public_id'   => $this->IdGenerator->generate(),
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
