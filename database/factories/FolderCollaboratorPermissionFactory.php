<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Permission;
use App\Models\FolderCollaboratorPermission;
use App\Models\FolderPermission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;

/**
 * @extends Factory<FolderCollaboratorPermission>
 */
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
            'permission_id' => static::$permissionTypes->where('name', Permission::VIEW_BOOKMARKS->value)->sole()->id
        ]);
    }

    public function inviteUser(): self
    {
        return $this->state([
            'permission_id' => static::$permissionTypes->where('name', Permission::INVITE_USER->value)->sole()->id
        ]);
    }

    public function addBookmarksPermission(): self
    {
        return $this->state([
            'permission_id' => static::$permissionTypes->where('name', Permission::ADD_BOOKMARKS->value)->sole()->id
        ]);
    }

    public function removeBookmarksPermission(): self
    {
        return $this->state([
            'permission_id' => static::$permissionTypes->where('name', Permission::DELETE_BOOKMARKS->value)->sole()->id
        ]);
    }

    public function updateFolderPermission(): self
    {
        return $this->state([
            'permission_id' => static::$permissionTypes->where('name', Permission::UPDATE_FOLDER->value)->sole()->id
        ]);
    }
}
