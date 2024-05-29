<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityType;
use App\Models\Folder;
use App\Models\FolderActivity;
use Illuminate\Contracts\Support\Arrayable;

final class CreateFolderActivity
{
    public function __construct(private readonly ?ActivityType $type = null)
    {
    }

    public function create(Folder $folder, Arrayable|array $data, ActivityType $type = null): FolderActivity
    {
        $type = $type ??= $this->type;

        if ($data instanceof Arrayable) {
            $data = $data->toArray();
        }

        return $folder->activities()->create([
            'type' => $type,
            'data' => $data
        ]);
    }
}
