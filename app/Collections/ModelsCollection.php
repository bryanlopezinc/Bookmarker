<?php

declare(strict_types=1);

namespace App\Collections;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Collection;

final class ModelsCollection extends Collection
{
    public function findUserById(int $userId): User
    {
        return $this->whereInstanceOf(User::class)
            ->filter(fn (User $user) => $user->id === $userId)
            ->first(default: new User());
    }

    public function findFolderById(int $id): Folder
    {
        return $this->whereInstanceOf(Folder::class)
            ->filter(fn (Folder $folder) => $folder->id === $id)
            ->first(default: new Folder());
    }
}
