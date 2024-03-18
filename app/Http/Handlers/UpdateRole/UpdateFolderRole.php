<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateRole;

use App\Contracts\FolderRequestHandlerInterface;
use App\Models\Folder;
use App\Models\FolderRole;

final class UpdateFolderRole implements FolderRequestHandlerInterface
{
    public function __construct(private readonly string $role)
    {
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        FolderRole::query()->where('folder_id', $folder->id)->update(['name' => $this->role]);
    }
}
