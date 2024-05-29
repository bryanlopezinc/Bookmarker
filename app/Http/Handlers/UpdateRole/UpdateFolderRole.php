<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateRole;

use App\Models\Folder;
use App\Models\FolderRole;

final class UpdateFolderRole
{
    public function __construct(private readonly string $role)
    {
    }

    public function __invoke(Folder $folder): void
    {
        FolderRole::query()->where('folder_id', $folder->id)->update(['name' => $this->role]);
    }
}
