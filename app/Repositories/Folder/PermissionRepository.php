<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\Enums\Permission;
use App\Models\FolderPermission;
use Illuminate\Support\Collection;

final class PermissionRepository
{
    /**
     * @var array<array{
     *   id: int,
     *   name: string,
     *   created_at: string
     *  }>
     */
    private const ROWS = [
        ['id' => 1, 'name' => 'ADD_BOOKMARKS',    'created_at' => '2023-12-12 15:48:29'],
        ['id' => 2, 'name' => 'DELETE_BOOKMARKS', 'created_at' => '2023-12-12 15:48:29'],
        ['id' => 3, 'name' => 'INVITE_USER',      'created_at' => '2023-12-12 15:48:29'],
        ['id' => 4, 'name' => 'UPDATE_FOLDER',    'created_at' => '2023-12-12 15:48:29'],
    ];

    /**
     * @param iterable<int> $ids collectable ids
     *
     * @return Collection<FolderPermission>
     */
    public function findManyById(iterable $ids): Collection
    {
        return collect(self::ROWS)->whereIn('id', collect($ids))->mapInto(FolderPermission::class);
    }

    /**
     * @param iterable<string> $names collectable permission names
     *
     * @return Collection<FolderPermission>
     */
    public function findManyByName(iterable $names): Collection
    {
        $names = collect($names)->map(function (string|Permission $name) {
            if (!is_string($name)) {
                $name = $name->value;
            }

            return $name;
        });

        return collect(self::ROWS)->whereIn('name', $names)->mapInto(FolderPermission::class);
    }

    public function findByName(string|Permission $name): FolderPermission
    {
        if ($name instanceof Permission) {
            $name = $name->value;
        }

        return $this->findManyByName([$name])->sole();
    }
}
