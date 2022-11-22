<?php

namespace Database\Seeders;

use App\Models\FolderPermission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class FolderPermissionsSeeder extends Seeder
{
    private const PERMISSIONS = [
        'viewBookmarks',
        'addBookmarks',
        'deleteBookmarks',
        'inviteUser',
        'updateFolder'
    ];

    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $seeded = FolderPermission::all(['name'])->pluck('name');

        collect(self::PERMISSIONS)
            ->filter(fn (string $permission) => !$seeded->contains($permission))
            ->map(fn (string $permission) => [
                'name' => $permission,
                'created_at' => now()
            ])
            ->whenNotEmpty(function (Collection $permissions) {
                FolderPermission::insert($permissions->all());
            });
    }
}
