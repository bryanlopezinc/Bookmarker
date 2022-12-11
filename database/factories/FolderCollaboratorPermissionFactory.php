<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FolderCollaboratorPermission;
use App\Models\FolderPermission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;

final class FolderCollaboratorPermissionFactory extends Factory
{
    protected $model = FolderCollaboratorPermission::class;
    protected static Collection $permissionTypes;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [];
    }

    /**
     * Configure the model factory.
     *
     * @return $this
     */
    public function configure()
    {
        if (!isset(static::$permissionTypes)) {
            static::$permissionTypes = FolderPermission::all(['name', 'id']);
        }

        return $this->afterMaking(function (FolderCollaboratorPermission $model) {
            if (!$model->offsetExists($offset = 'permission_id')) {
                $model->setAttribute($offset, static::$permissionTypes->random()->id);
            }
        });
    }

    public function user(int $id): self
    {
        return $this->state(['user_id' => $id]);
    }

    public function folder(int $id): self
    {
        return $this->state(['folder_id' => $id]);
    }

    public function viewBookmarksPermission(): self
    {
        return $this->state([
            'permission_id' => static::$permissionTypes->where('name', FolderPermission::VIEW_BOOKMARKS)->sole()->id
        ]);
    }

    public function inviteUser(): self
    {
        return $this->state([
            'permission_id' => static::$permissionTypes->where('name', FolderPermission::INVITE)->sole()->id
        ]);
    }

    public function addBookmarksPermission(): self
    {
        return $this->state([
            'permission_id' => static::$permissionTypes->where('name', FolderPermission::ADD_BOOKMARKS)->sole()->id
        ]);
    }

    public function removeBookmarksPermission(): self
    {
        return $this->state([
            'permission_id' => static::$permissionTypes->where('name', FolderPermission::DELETE_BOOKMARKS)->sole()->id
        ]);
    }

    public function updateFolderPermission(): self
    {
        return $this->state([
            'permission_id' => static::$permissionTypes->where('name', FolderPermission::UPDATE_fOLDER)->sole()->id
        ]);
    }
}
