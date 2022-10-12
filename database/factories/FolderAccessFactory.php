<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FolderAccess;
use App\Models\FolderPermission;
use Illuminate\Database\Eloquent\Factories\Factory;

final class FolderAccessFactory extends Factory
{
    protected $model = FolderAccess::class;

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
        return $this->afterMaking(function (FolderAccess $model) {
            if (!$model->offsetExists($offset = 'permission_id')) {
                $model->setAttribute($offset, FolderPermission::query()->first()->id);
            }
        });
    }

    public function user(int $id): self
    {
        return $this->state([
            'user_id' => $id,
        ]);
    }

    public function folder(int $id): self
    {
        return $this->state([
            'folder_id' => $id,
        ]);
    }

    public function viewBookmarksPermission(): self
    {
        return $this->state([
            'permission_id' => FolderPermission::query()
                ->where('name', FolderPermission::VIEW_BOOKMARKS)
                ->sole()
                ->id
        ]);
    }

    public function addBookmarksPermission(): self
    {
        return $this->state([
            'permission_id' => FolderPermission::query()
                ->where('name', FolderPermission::ADD_BOOKMARKS)
                ->sole()
                ->id
        ]);
    }
}
